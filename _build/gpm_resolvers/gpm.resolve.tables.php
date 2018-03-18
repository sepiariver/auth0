<?php
/**
 * Resolve creating db tables
 *
 * THIS RESOLVER IS AUTOMATICALLY GENERATED, NO CHANGES WILL APPLY
 *
 * @package auth0
 * @subpackage build
 *
 * @var mixed $object
 * @var modX $modx
 * @var array $options
 */

if ($object->xpdo) {
    $modx =& $object->xpdo;
    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            $modelPath = $modx->getOption('auth0.core_path', null, $modx->getOption('core_path') . 'components/auth0/') . 'model/';
            
            $modx->addPackage('auth0', $modelPath, null);


            $manager = $modx->getManager();

            $manager->createObjectContainer('Auth0XToken');

            break;
    }
}

return true;