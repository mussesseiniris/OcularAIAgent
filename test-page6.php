<?php
require __DIR__ . '/vendor/autoload.php';
$parser = new Smalot\PdfParser\Parser();
$pdf    = $parser->parseFile(__DIR__ . '/packages/chatbot/Resources/Private/Pdfs/Positioning-and-tone-of-voice.pdf');
$pages  = $pdf->getPages();
$text   = $pages[6]->getText();
echo 'Length: ' . strlen(trim($text)) . ' chars' . PHP_EOL;
echo $text . PHP_EOL;
