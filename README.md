# Zend Framework 2 Gettext File Editor

Want a PHP/Twig file parser that's local to your project, with a web GUI to deal with gettext intricacies?  This project is it!

## How it works

This project combines a parser, an PHP PO file editor, and gettext CLI commands to do what programs like PoEdit do at the surface. You use translation markers in your code like:

```php
// php code
_( "this string will automatically get picked up by gettext");
$sm->get('translator')->translate( "So will this one" );
$el->setLabel( "This one too");
```

```twig
// twig code
{% trans %}This is a singular translation{% endtrans %}
{% trans "This is also a singular translation" %}

{% set apples = 3 %}
{% trans %}
There is one apple in the cart.
{% plural apples %}
There are {{ apples }} in the cart.
{% endtrans %}
```

Then, when you use the web GUI you'll see a set of fields where you can edit all of your translatable-text.

## Installation

To install the packages:

    composer require saeven/zf2-poeditor

You have to make sure that you have gettext installed as well.  Check your system with these commands:

    which msgfmt
    which gettext
    which xgettext
    which msguniq

If they all exist, you are in business. Otherwise, Google how to get gettext binaries installed on your system.


## Configuration

Once you have downloaded the Composer package, copy the autoload config to your project:

    cp ./vendor/saeven/zf2-poeditor/config/autoload/circlical.translator.local.php ./config/autoload

In that file, you'll see these config keys:

| Key Name    | Description                               |
|-------------|-------------------------------------------|
| xgettext    | Full path to your xgettext executable.     |
| msgcat      | Full path to your msgcat executable.       |
| msgfmt      | Full path to your msgfmt executable.       |
| backup_dir  | The folder where .po files are backed up with each pass. **must be writable** |
| cache_dir   | The cache folder where the temporary .pot files are stored during compilation. **must be writable** |
| stub_functions | *See 'Stubs' below* |
| stub_filters | *See 'Stubs' below* |


### Stubs

If you are coming from the camp that combines PoEdit with a Twig extractor, you'll remember those instances where
you'd get a giant error message but no reason why.  90% of the time, it's because a developer had added a custom
filter or helper that wasn't registered with the extractor.  The outcome is breakage: the extractor dies, PoEdit can't
ferry the error, and you're stuck cutting and pasting messages to identify the culprit.

Enter stubs.

When you are using this package, the Twig errors will be reported in plain sight if you meet this circumstance.

![Error Sample]
(http://i.imgur.com/GW2LmIr.png)

In that image, you can see that the **GrilledCheese** helper is not registered (what a shame, so tasty).  Somewhere in the
Twig files, there's a `{{ GrilledCheese }}` that's causing the problem.  To fix this:

* Open up the translator config
* Add 'GrilledCheese' to the function stubs
* Save the config
* Click refresh inside the translator

Filters work similarly, the error message will guide you properly.


## Contributing

This is a first release, but it works very well.  It's my hope to PSR-7 this with Expressive so that all frameworks can
benefit from its use.  Throwing out to the world can only make it better!  Have an idea or an issue you can solve? PR it
up, let's make this tool a solid alternative to PoEdit together!

Thanks in advance!





