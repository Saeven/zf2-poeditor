<?php

namespace CirclicalTranslationEditor\Controller;

use Circlical\PoEditor\HeaderBlock;
use Circlical\PoEditor\PoEditor;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;
use Zend\Config\Reader\Ini as ConfigReader;
use Zend\Config\Writer\Ini as ConfigWriter;
use Zend\Config\Config;
use Zend\Mvc\MvcEvent;


class IndexController extends AbstractActionController
{

    const SCAN_SELECTIVE = 'scan_selective';
    const SCAN_ALL = 'scan_all';

    /**
     * Save a setting into translation.ini
     * @param $section
     * @param $subsection
     * @param $value
     */
    public function setConfig( $section, $subsection, $value )
    {
        $cfg = $this->getConfig();
        if( !isset( $cfg->$section ) )
            $cfg->$section = [];

        if( $subsection )
            $cfg->$section->$subsection = $value;
        else
            $cfg->$section = $value;

        $config = $this->getServiceLocator()->get( 'config' );
        $config = $config['circlical']['translation_editor'];
        $cache_dir = $config['cache_dir'];
        $translator_config = $cache_dir . '/translator.ini';

        if( !file_exists( $translator_config ) )
        {
            @mkdir( dirname( $translator_config ), 0755, true );
            @touch( $translator_config );
        }

        $writer = new ConfigWriter();
        $writer->toFile( $translator_config, $cfg, false );
    }

    /**
     * Fetch the entire translation.ini, or a part
     * @return Config|string
     */
    private function getConfig( $section = null, $setting = null )
    {
        $config = $this->getServiceLocator()->get( 'config' );
        $config = $config['circlical']['translation_editor'];
        $cache_dir = $config['cache_dir'];
        $translator_config = $cache_dir . '/translator.ini';
        $params = [];
        if( file_exists( $translator_config ) )
        {
            $reader    = new ConfigReader();
            $params    = $reader->fromFile( $translator_config );
        }

        $config = new Config($params,true);
        if( $section )
        {
            if( isset( $config->$section ) )
            {
                if( $setting )
                {
                    return isset($config->$section->$setting) ? $config->$section->$setting : null;
                }
                else
                {
                    return $config->$section;
                }
            }
            else
            {
                return null;
            }
        }
        return $config;
    }


    /**
     * Convenience method to help with code readability -- gets the type of scan, scan_selective or scan_all
     * @return string
     */
    private function getScanType()
    {
        $type = $this->getConfig('general','scan_type');
        return $type ?: 'all';
    }

    private function listTwigFiles()
    {
        $cmd = 'find ' . getcwd() . '/module -name "*.twig"';
        $ret = shell_exec( $cmd );
        $ret = preg_split('/$\R?^/m', $ret );
        $ret = array_map( function( $x ){
            return str_replace( getcwd() . '/module/', '', $x );
        }, $ret );
        return $ret;
    }

    private function listPhpFiles()
    {
        $cmd = 'find ' . getcwd() . '/module -name "*.php"';
        $ret = shell_exec( $cmd );
        $ret = preg_split('/$\R?^/m', $ret );
        $ret = array_map( function( $x ){
            return str_replace( getcwd() . '/module/', '', $x );
        }, $ret );
        return $ret;
    }

    private function listLocales()
    {
        $cmd = "find " . getcwd() . "/module/*/language/* -maxdepth 0 -type d";
        $ret = shell_exec($cmd);
        $locales = array_map( function( $x ){
            return trim( substr( $x, strrpos( $x, DIRECTORY_SEPARATOR ) + 1 ) );
        }, preg_split('/$\R?^/m', $ret ) );
        $locales = array_unique( $locales );
        return $locales;
    }


    /**
     * Execute a guard function, gives you the opportunity to bail or die if conditions aren't met
     */
    public function onDispatch(MvcEvent $e){
        $config     = $this->getServiceLocator()->get( 'config' );
        $config     = $config['circlical']['translation_editor'];
        if( !empty( $config['guard'] ) && is_callable( $config['guard']) )
        {
            $config['guard']();
        }
        parent::onDispatch( $e );
    }


    /**
     * Discover all translatable files, load them into view with checkboxes
     * @return ViewModel
     */
    public function indexAction()
    {
        $vm = new ViewModel();
        $vm->setVariables([
          'twig_files' => $this->listTwigFiles(),
          'php_files' => $this->listPhpFiles(),
          'locales' => $this->listLocales(),
          'scan_type' => $this->getScanType(),
          'checked' => $this->getSelectedFiles(true),
        ]);

        $vm->setVariables([
			'project_id' => $this->getConfig( 'config', 'project_id' ),
	        'report_msgid_bugs' => $this->getConfig( 'config', 'report_msgid_bugs' ),
	        'copyright' => $this->getConfig( 'config', 'copyright' ),
	    ]);

        return $vm;
    }


    /**
     * Called when the automatic/manual buttons are toggled on the main view, this stores into a flatfile, the
     * scan type that was selected.  It's later used at the compile step.
     */
    public function setScanTypeAction()
    {
        $this->setConfig( 'general', 'scan_type', $this->params()->fromQuery('type') );
        return new JsonModel(['success'=>true]);
    }


    /**
     * Set a particular file for translation: "Yes, I would like to translate ..."
     * Receives 'type' and 'files[]'
     *
     * @return JsonModel
     */
    public function setAction()
    {
        $response = [ 'success' => false ];
        $sm = $this->getServiceLocator();

        try
        {
            $params = $this->params();
            $type   = $params->fromPost( 'type' );
            $this->setConfig( $type, null, $params->fromPost( 'files' ) );
            $response['success'] = true;
        }
        catch( \Exception $x )
        {
            $response['message'] = $x->getMessage();
        }

        return new JsonModel( $response );
    }

    /**
     * Here's the meat & potatoes of this job.  Compile raw files using the twig gettext extractor, then parse the mo files to show a usable interface.
     * What's more, you're getting a locale flag, and this will allow you to generate the proper pluralization schema.
     */
    public function compileAction()
    {
	    $response       = [ 'success' => false ];

        try
        {
            $locale = preg_replace( '/[^a-zA-Z_]/', "", $this->params()->fromQuery( 'locale' ) );

            if( !$locale )
            {
                throw new \Exception( "Locale is required!" );
            }

            $config     = $this->getServiceLocator()->get( 'config' );
            $config     = $config['circlical']['translation_editor'];
            $xgettext   = $config['xgettext'];
            $msgcat     = $config['msgcat'];
            $backup_dir = $config['backup_dir'];
            $cache_dir  = $config['cache_dir'];


            // pull the files in the database
            $files = $this->getSelectedFiles();
            $modules = [];

            if( !empty($files['twig']) )
            {
                $twig_file_list = array_map( function ( $str ){
                    return sprintf( '"' . 'module/' . '%s"', trim( $str ) );
                }, $files['twig'] );

                // group twig files by module
                foreach( $twig_file_list as $f )
                {
                    preg_match( '#^"module/([A-Za-z]*?)/#us', $f, $matches );
                    if( $matches )
                        $modules[$matches[1]]['twig'][] = $f;
                }
            }

            if( !empty( $files['php'] ) )
            {
                $php_file_list = array_map( function ( $str ){
                    return sprintf( '"' . 'module/' . '%s"', trim( $str ) );
                }, $files['php'] );

                // group php files by module
                foreach( $php_file_list as $f )
                {
                    preg_match( '#^"module/([A-Za-z]*?)/#us', $f, $matches );
                    if( $matches )
                        $modules[$matches[1]]['php'][] = $f;
                }
            }

            // first, grind the new files
	        @mkdir( $backup_dir, 0755, true );
            $response['success'] = true;
            foreach( $modules as $m => $list )
            {

	            $module_twig_pot = $cache_dir . '/' . $m . '.twig.pot';
	            $module_php_pot  = $cache_dir . '/' . $m . '.php.pot';
	            $final_pot       = $cache_dir . '/' . $m . '.pot';
	            $mergelist       = [ ];

	            // do we have twig files?
	            if( !empty( $list['twig'] ) )
	            {
		            $cmd = getcwd() . "/vendor/saeven/circlical-twig-extractor/twig-gettext-extractor --sort-output --force-po " .
			            '-o "' . $module_twig_pot . '" ' .
			            '--from-code=UTF-8 -ktranslate -L PHP --exec ' . $xgettext . ' ' .
			            (
				            !empty( $config['stub_functions'] ) ? "--functions " . implode(",", $config['stub_functions']) . ' '  : ''
			            ) .
                        (
				            !empty( $config['stub_filters'] ) ? "--filters " . implode(",", $config['stub_filters']) . ' '  : ''
			            ) .
			            '--files ' . implode( " ", $list['twig'] );

		            $ret = shell_exec( $cmd . ' 2>&1' );
		            if( strlen( $ret ) )
			            throw new \Exception( $ret );

		            $mergelist[] = $module_twig_pot;
	            }

	            // do we have php files?
	            if( !empty( $list['php'] ) )
	            {
		            $cmd = $xgettext . ' --language=PHP --add-comments=TRANSLATORS --add-comments=translators: --force-po ' .
			            '-o "' . $module_php_pot . '" ' .
			            '--from-code=UTF-8  -ktranslate -k_ -ksetLabel -ksetValue -ksetLegend -k_refresh ' .
			            implode( " ", $list['php'] );

		            $ret = shell_exec( $cmd );
		            if( strlen( $ret ) )
			            throw new \Exception( $ret );

		            $mergelist[] = $module_php_pot;
	            }

	            // merge the two files into a final POT file
	            shell_exec( $msgcat . " --use-first " . implode( " ", $mergelist ) . " > $final_pot" );
            }

            // then, backup the old files
            foreach( $modules as $m => $list )
            {
                $module_po_file = getcwd() . '/module/' . $m . '/language/' . $locale . '/LC_MESSAGES/default.po';
                $new_po_file    = $cache_dir . '/' . $m . '.pot';

                if( file_exists( $module_po_file ) )
                {
                    copy( $module_po_file, $backup_dir . '/' . $m . '.default.' . time() . '.po' );

                    $module_parser = new PoEditor( $module_po_file );
                    $module_parser->parse();
                    $new_parser = new PoEditor( $new_po_file );
                    $new_parser->parse();

                    // bring old translations into the new context
                    foreach( $new_parser->getBlocks() as $key => $block )
                    {
                        if( $module_parser->getBlockLike( $block ) )
                            continue;

                        $module_parser->addBlock( $block );
                    }

                    // write the new module into default.po
                    file_put_contents( $module_po_file, $module_parser->compile() );
                }
                else
                {
                    @mkdir( dirname( $module_po_file ), 0755, true );
                    @copy( $new_po_file, $module_po_file );
                }
            }

	        $response['success'] = true;
        }
        catch( \Exception $x )
        {
	        $response['success'] = false;
			$response['message'] = $x->getMessage();
	        $response['type'] = get_class( $x );
        }

        return new JsonModel( $response );
    }

    /**
     * Fetch a module-based editor
     * @return ViewModel
     * @throws \Exception
     */
    public function getEditorAction()
    {
        $vm = new ViewModel();
        $locale = preg_replace('/[^a-zA-Z_]/',"", $this->params()->fromPost('locale') );

        $vm->setTerminal(true);
        $vm->setTemplate('circlical-translation-editor/index/editor');
        $vm->setVariable('locale', $locale);

        // discover eligible modules
        $files = $this->getSelectedFiles();
        $file_list = array_map( function( $str ){ return sprintf('"' . 'module/' . '%s"', trim($str)); }, $files['twig'] );

        // go through files, group by module
        $modules = [];
        foreach( $file_list as $f )
        {
            $matches = [];
            preg_match('#^"module/([A-Za-z]*?)/#us', $f, $matches );
            if( $matches )
            {
                $modules[] = $matches[1];
            }
        }

        $module_entries = [];
        foreach( $modules as $m )
        {
            $module_po_file = getcwd() . '/module/' . $m . '/language/' . $locale . '/LC_MESSAGES/default.po';
            $editor = new PoEditor( $module_po_file );
            $editor->parse();
            $module_entries[$m] = $editor->getBlocks();
        }

        $vm->setVariable('module_entries', $module_entries);

	    $vm->setVariables([
			'language_team' => $this->getConfig( 'language_config_' . $locale, 'language_team' ),
	        'charset' => $this->getConfig( 'language_config_' . $locale, 'charset' ),
	        'plural_forms' => $this->getConfig( 'language_config_' . $locale, 'plural_forms' ),
	    ]);

        return $vm;
    }

	/**
	 * Save the language configuration.  This stuff ends up in the po file header.
	 */
	public function saveLanguageConfigAction()
	{
		$response = ['success' => false];

		try
		{
			$accepted = [ 'language_team', 'charset', 'plural_forms' ];
			$locale = preg_replace('/[^a-zA-Z_]/',"", $this->params()->fromRoute('locale') );

			if( !$locale )
				throw new \Exception( "Locale is required" );

			foreach( $accepted as $k )
				$this->setConfig( "language_config_{$locale}", $k, $this->params()->fromPost($k) );

			$response['success'] = true;
		}
		catch( \Exception $x )
		{
			$response['message'] = $x->getMessage();
		}

		return new JsonModel($response);
	}

    public function saveConfigAction()
    {
        $response = ['success' => false];

		try
		{
			$accepted = [ 'project_id', 'report_msgid_bugs', 'copyright' ];
			foreach( $accepted as $k )
				$this->setConfig( "config", $k, $this->params()->fromPost($k) );

			$response['success'] = true;
		}
		catch( \Exception $x )
		{
			$response['message'] = $x->getMessage();
		}

		return new JsonModel($response);
    }


    /**
     * Receive a raw post body that's a JSON structure, rifle through it and save matches.  The structure is:
     *
     *  Module: {
     *      Strings: {
     *          Singular: {
     *          Plural: {
     */
    public function saveAction()
    {
        $response['success'] = false;
        $locale = preg_replace('/[^a-zA-Z_]/',"", $this->params()->fromRoute('locale') );

        $config = $this->getServiceLocator()->get( 'config' );
        $config = $config['circlical']['translation_editor'];
        $msgfmt = $config['msgfmt'];

        try
        {
            if( !$this->getRequest()->isPost() )
                throw new \Exception( "POST expected, got something else." );

            if( !$this->params()->fromRoute('locale') )
                throw new \Exception( "Locale required" );

            $data = $this->getRequest()->getContent();
            $json = json_decode( $data, true );



            foreach( array_keys( $json ) as $module )
            {
                $module_po_file = getcwd() . '/module/' . $module . '/language/' . $locale . '/LC_MESSAGES/default.po';
                $module_mo_file = getcwd() . '/module/' . $module . '/language/' . $locale . '/LC_MESSAGES/default.mo';

                $parser = new PoEditor( $module_po_file );
                $parser->parse();

                foreach( $json[$module] as $v )
                {
	                $key = json_encode([ 'context' => $v['context'], 'id' => $v['id'] ]);

                    if( $block = $parser->getBlockWithKey( $key ) )
                    {
	                    if( !empty($v['singular']) )
	                    {
		                    $block->setMsgstr( $v['singular'] );
	                    }

	                    if( !empty($v['plural']) )
	                    {
		                    foreach( $v['plural'] as $form => $string )
			                    $block->setPluralForm( $form, $string );
	                    }
                    }
	                else
	                {
		                throw new \Exception( "The editor no longer matches the code?  This block couldn't be found anymore!\n" .  $v['id'] );
	                }
                }

	            /** @var HeaderBlock $header_block */
                $header_block = $parser->getBlockWithKey("");
				$header_block->setValue("Language-Team", $this->getConfig( 'language_config_' . $locale, 'language_team' ));
                $header_block->setValue("Content-Type", "text/plain; charset=" . $this->getConfig( 'language_config_' . $locale, 'charset' ));
                $header_block->setValue("Plural-Forms", $this->getConfig( 'language_config_' . $locale, 'plural_forms' ));
                $header_block->setValue("Language", $locale );

                file_put_contents( $module_po_file, $parser->compile() );
                shell_exec( $msgfmt . ' ' . $module_po_file . ' -o ' . $module_mo_file );

                $response['cmd'][$module] = $msgfmt . ' ' . $module_po_file . ' -o ' . $module_mo_file;
                $response['success'] = true;
            }

        }
        catch( \Exception $x )
        {
            $response['message'] = $x->getMessage();
        }

        return new JsonModel( $response );

    }

    /**
     * Get the list of files that should be processed based on scan type
     * @param bool $force_manual Get the manually-picked list only
     * @return array
     */
    private function getSelectedFiles( $force_manual = false )
    {
        $cfg = $this->getConfig();
        if( $force_manual || $cfg->general->scan_type == self::SCAN_SELECTIVE )
        {
            return [
                'php'  => !empty($cfg->php) ? $cfg->php->toArray() : [ ],
                'twig' => !empty($cfg->twig) ? $cfg->twig->toArray() : [ ],
            ];
        }

        return [
            'php' => $this->listPhpFiles(),
            'twig' => $this->listTwigFiles(),
        ];
    }
}
