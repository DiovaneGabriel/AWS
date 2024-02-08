<?php

use DBarbieri\Aws\S3;
use DBarbieri\Aws\SQS;
use DBarbieri\Graylog\Graylog;

// use DBarbieri\Graylog\Graylog;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '../.env');
$dotenv->load();

$graylog = new Graylog('http://graylog', 12201);

// $s3 = new S3($_ENV['AWSS3_KEY'], $_ENV['AWSS3_SECRET'], $_ENV['AWSS3_REGION'], "presto-private");
// $s3->setGraylog($graylog);

// $return = $s3->send( file_get_contents("./composer.json"), "novo_teste/composer.json", true);
// $return = $s3->delete( "novo_teste/1704228424.4395composer.json", true);
// $return = $s3->doesObjectExists( "novo_teste/1704228424.4395composer.json");
// $return = $s3->get( "composer.json");
// $return = $s3->list("presto-private");

// $return = $s3->getByUrl("https://presto-private.s3.us-east-2.amazonaws.com/51941986000135/NFSe/8899738882205194198620241229122023253915.xml");
// $return = $s3->deleteByUrl("https://presto-private.s3.us-east-2.amazonaws.com/novo_teste/composer.json");

$sqs = new SQS($_ENV['AWSSQS_KEY'], $_ENV['AWSSQS_SECRET'], $_ENV['AWSSQS_REGION'], $_ENV['AWSSQS_URL']);
$sqs->setGraylog($graylog);

$data = [];
$data['nome'] = "Diovane";
$data['sobrenome'] = "Barbieri Gabriel";
$data['time'] = microtime();

// $return = $sqs->send(json_encode($data));
$return = $sqs->receive(null, 1);
// $return = $sqs->delete($return[0]["ReceiptHandle"]);

echo '<pre>';
var_dump($return);
die();
