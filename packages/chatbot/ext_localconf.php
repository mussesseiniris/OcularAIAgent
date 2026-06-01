<?php

defined('TYPO3') or die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptSetup(
    '@import "EXT:chatbot/Configuration/TypoScript/setup.typoscript"'
);

(function () {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'Chatbot',
        'Chatbot',
           [
            \Ocular\Chatbot\Controller\ChatController::class => 'ask'
        ], 
        [
            \Ocular\Chatbot\Controller\ChatController::class => 'ask'
        ]
    );
})();


// ExtensionUtility::configurePlugin(
//     // extension name, matching the PHP namespaces (but without the vendor)
//     'BlogExample',
//     // arbitrary, but unique plugin name (not visible in the backend)
//     'PostSingle',
//     // all actions
//     [PostController::class => 'show', CommentController::class => 'create'],
//     // non-cacheable actions
//     [CommentController::class => 'create'],
//     ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
// );