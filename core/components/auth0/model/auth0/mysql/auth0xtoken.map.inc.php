<?php
/**
 * @package auth0
 */
$xpdo_meta_map['Auth0XToken']= array (
  'package' => 'auth0',
  'version' => '1.1',
  'table' => 'auth0_x_tokens',
  'extends' => 'xPDOSimpleObject',
  'tableMeta' => 
  array (
    'engine' => 'InnoDB',
  ),
  'fields' => 
  array (
    'x_token' => NULL,
    'timestamp' => 'CURRENT_TIMESTAMP',
    'expires' => NULL,
  ),
  'fieldMeta' => 
  array (
    'x_token' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '2000',
      'phptype' => 'string',
    ),
    'timestamp' => 
    array (
      'dbtype' => 'timestamp',
      'phptype' => 'timestamp',
      'null' => false,
      'default' => 'CURRENT_TIMESTAMP',
    ),
    'expires' => 
    array (
      'dbtype' => 'timestamp',
      'phptype' => 'timestamp',
      'null' => true,
    ),
  ),
);
