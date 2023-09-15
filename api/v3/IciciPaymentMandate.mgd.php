<?php
// This file declares a managed database record of type "Job".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return [
  [
    'name' => 'Cron:Job.IciciEnachVerifymandate',
    'entity' => 'Job',
    'params' => [
      'version' => 3,
      'name' => 'IciciEnach: Verify Mandate',
      'description' => 'IciciEnach: Verify Mandate',
      'run_frequency' => 'Hourly',
      'api_entity' => 'IciciPaymentMandate',
      'api_action' => 'verifymandate',
      'parameters' => '',
    ],
  ],
  [
    'name' => 'Cron:Job.IciciEnachScheduling',
    'entity' => 'Job',
    'params' => [
      'version' => 3,
      'name' => 'IciciEnach: Transaction Scheduling',
      'description' => 'IciciEnach: Transaction Scheduling',
      'run_frequency' => 'Hourly',
      'api_entity' => 'IciciPaymentMandate',
      'api_action' => 'scheduletransaction',
      'parameters' => '',
    ],
  ],
  [
    'name' => 'Cron:Job.IciciEnachVerifytransaction',
    'entity' => 'Job',
    'params' => [
      'version' => 3,
      'name' => 'IciciEnach: Transaction Verification',
      'description' => 'IciciEnach: Transaction Verification',
      'run_frequency' => 'Hourly',
      'api_entity' => 'IciciPaymentMandate',
      'api_action' => 'verifytransaction',
      'parameters' => '',
    ],
  ]
];
