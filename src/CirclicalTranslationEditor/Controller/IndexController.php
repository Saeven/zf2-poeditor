<?php

namespace CirclicalTranslationEditor\Controller;

use Circlical\PoEditor\PoEditor;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    /**
     * Discover all translatable files, load them into view with checkboxes
     * @return ViewModel
     */
    public function indexAction()
    {
        $vm = new ViewModel();

        // find all twig files
        $cmd = 'find ' . getcwd() . '/module -name "*.twig"';
        $ret = shell_exec( $cmd );
        $ret = preg_split('/$\R?^/m', $ret );
        $ret = array_map( function( $x ){
            return str_replace( getcwd() . '/module/', '', $x );
        }, $ret );
        $vm->setVariable( 'twig_files', $ret );



         // find all php files
        $cmd = 'find ' . getcwd() . '/module -name "*.php"';
        $ret = shell_exec( $cmd );
        $ret = preg_split('/$\R?^/m', $ret );
        $ret = array_map( function( $x ){
            return str_replace( getcwd() . '/module/', '', $x );
        }, $ret );
        $vm->setVariable( 'php_files', $ret );


        $vm->setVariable( 'checked', $this->getSelectedFiles() );

        $cmd = "find " . getcwd() . "/module/*/language/* -maxdepth 0 -type d";
        $ret = shell_exec($cmd);
        $locales = array_map( function( $x ){
            return trim( substr( $x, strrpos( $x, DIRECTORY_SEPARATOR ) + 1 ) );
        }, preg_split('/$\R?^/m', $ret ) );
        $locales = array_unique( $locales );
        $vm->setVariable( 'locales', $locales );

        return $vm;
    }

    /**
     * Set a particular file for translation: "Yes, I would like to translate ..."
     * @return JsonModel
     */
    public function setAction()
    {
        $response = [ 'success' => false ];
        $sm = $this->getServiceLocator();
        $config = $sm->get('config');

        try
        {
            $DBM = $this->getDatabase();
            $DBA = $DBM->getAdapter();

            // delete old
            $del = $DBM->delete( 'dev_translation_files' )
                ->where( [
                    'type' => $this->params()->fromPost( 'type' ),
                ] );

            $str = $DBM->buildSqlString( $del, $DBA );
            $DBA->query( $str, Adapter::QUERY_MODE_EXECUTE );

            // insert new
            $ins = $DBM->insert( 'dev_translation_files' )
                ->values( [
                    'type'             => $this->params()->fromPost( 'type' ),
                    'serialized_files' => json_encode( $this->params()->fromPost( 'files' ) ),
                ] );

            $str = $DBM->buildSqlString( $ins, $DBA );
            $DBA->query( $str, Adapter::QUERY_MODE_EXECUTE );
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
            $locale         = preg_replace( '/[^a-zA-Z_]/', "", $this->params()->fromQuery( 'locale' ) );

	        if( !$locale )
                throw new \Exception( "Locale is required!" );

            $config         = $this->getServiceLocator()->get( 'config' );
	        $config         = $config['circlical']['translation_editor'];
            $xgettext       = $config['xgettext'];
            $msgcat         = $config['msgcat'];
	        $backup_dir     = $config['backup_dir'];
	        $cache_dir      = $config['cache_dir'];


	        // pull the files in the database
	        $files          = $this->getSelectedFiles();
	        $twig_file_list = array_map( function ( $str ){
                return sprintf( '"' . 'module/' . '%s"', trim( $str ) );
            }, $files['twig'] );

            $php_file_list  = array_map( function ( $str ){
                return sprintf( '"' . 'module/' . '%s"', trim( $str ) );
            }, $files['php'] );

            $modules = [ ];

            // group twig files by module
            foreach( $twig_file_list as $f )
            {
                preg_match( '#^"module/([A-Za-z]*?)/#us', $f, $matches );
                if( $matches )
                    $modules[$matches[1]]['twig'][] = $f;
            }

            // group php files by module
            foreach( $php_file_list as $f )
            {
                preg_match( '#^"module/([A-Za-z]*?)/#us', $f, $matches );
                if( $matches )
                    $modules[$matches[1]]['php'][] = $f;
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
			            '--files ' . implode( " ", $list['twig'] );

		            $ret = shell_exec( $cmd );
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

	            $ret = shell_exec( $msgcat . " --use-first " . implode( " ", $mergelist ) . " > $final_pot" );
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
			$response['message'] = $x->getMessage();
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
        $vm->setTemplate( 'circlical-translation-editor/index/editor' );

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

        $vm->setVariable( 'module_entries', $module_entries );

        return $vm;
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
                $parser = new PoEditor( $module_po_file );
                $parser->parse();

                foreach( $json[$module] as $k => $v )
                {
                    $key = rawurldecode( $k );
                    $block = $parser->getBlockWithKey( $key );

                    if( !empty( $v['singular'] ) )
                    {
                        $block->setMsgstr( $v['singular'] );
                    }

                    if( !empty( $v['plural'] ) )
                    {
                        foreach( $v['plural'] as $form => $string )
                            $block->setPluralForm( $form, $string );
                    }
                }

                file_put_contents( $module_po_file, $parser->compile() );
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
     * Check the database for selected files
     * @return array
     */
    private function getSelectedFiles()
    {
        $DBM = $this->getDatabase();
        $DBA = $DBM->getAdapter();
        $sel = $DBM->select('dev_translation_files');
        $str = $DBM->buildSqlString( $sel, $DBA );
        $res = $DBA->query( $str, Adapter::QUERY_MODE_EXECUTE );

        $checked = [];
        foreach( $res as $r ){
            $files = json_decode( $r->serialized_files );
            foreach( $files as $f ){
                $checked[$r->type][] = $f;
            }
        }
        return $checked;
    }

    /**
     * Fetch a database
     * @return Sql
     */
    private function getDatabase(){
        $sm = $this->getServiceLocator();
	    $config = $sm->get('config');

        if( !empty($config['circlical']['translation_editor']['database_handle']) )
        {
            $database = $sm->get( $config['circlical']['translation_editor']['database_handle'] );
        }
        else
        {
            $database = $sm->get( 'Zend\Db\Adapter\Adapter' );
        }
        return new Sql( $database );
    }

}
