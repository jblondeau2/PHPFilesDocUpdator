<?php

require_once dirname(__FILE__).'/PHPFilesDocUpdator.class.php';

/**
 * Just associate a present path key to a subpackage to apply.
 * Example for a symfony 1.4 project.
 */
$filesTypeAssoc = array(
    'example' => 'Example',
    'test'    => 'Test',
);

/**
 * Options to apply.
 * These informations are to be completed to change them.
 *
 * realMode: set to 'true' if you want to apply modifications to files
 * generateDescription: generate class description if phpDoc headers have to be created
 * phpDoc: an array with phpDoc options to set
 */
$options = array(
    'realMode'            => true,
    'generateDescription' => true,
    'phpDoc'              => array(
        'package'    => 'Poney',
        'subpackage' => null,
        'version'    => '1.23',
        'author'     => 'Chuck Norris <chuck@norr.is>',
    ),
);

/**
 * Class instance creation.
 * Change first argument with the path of the folder you want to parse.
 */
$phpDocCheater = new PHPFilesDocUpdator('path/folder/to/parse', $options, $filesTypeAssoc);
$phpDocCheater->letsGo();