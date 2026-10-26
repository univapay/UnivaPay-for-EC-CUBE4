<?php



$loader = require __DIR__.'/vendor/autoload.php';
$loader->unregister();

// Preventing the plugin from loading its own PSR Logger interfaces
// Let EC-CUBE's Composer autoloader provide them
// As having 2 LoggerAwareInterface, one bundled and one being ECCUBE dependency can cause conflict
$loader->setPsr4('Psr\\Log\\', []);

// Keep the bundled UnivaPay SDK available
$loader->register(false);
