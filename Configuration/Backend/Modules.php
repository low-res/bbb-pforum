<?php

return [
    'web_pforumAdministration' => [
        'parent' => 'web',
        'access' => 'user',
        'iconIdentifier' => null,
        'labels' => 'LLL:EXT:pforum/Resources/Private/Language/locallang_mod_administration.xlf',
        'extensionName' => 'pforum',
        'controllerActions' => [
            'JWeiland\Pforum\Controller\AdministrationController' => [
                'index',
                'listHiddenTopics',
                'listHiddenPosts',
                'activateTopic',
                'activatePost',
            ],
        ],
    ],
];
