#!/usr/bin/php
<?php

require "vendor/autoload.php";


$sock = stream_socket_client("tcp://broker.hivemq.com:1883");


$pkt = new \PhpMqtt\Protocol\v5\Packet_CONNECT();
$pkt->cleanSession = true;
$pkt->keepAlive = 600;
$pkt->clientId = "pippoDwWeBbgAMZz";
// $pkt->sessionExpiration = 0x12131415;
// $pkt->receiveMaximum = 0x16;
// $pkt->maximumPacketSize = 0x17181920;
// $pkt->topicAliasMaximum = 0x21;
// $pkt->requestResponseInformation = true;
// $pkt->requestProblemInformation = true;
// $pkt->willTopic = "c/d";
// $pkt->willContent = "ee";
// $pkt->willRetain = true;
// $pkt->willQoS = 1;
// $pkt->willDelay = 0x33;
// $pkt->willFormat = 1;
// $pkt->willExpiration = 0x3435;
// $pkt->willContentType = "zz";
// $pkt->willResponseTopic = "e/f";
// $pkt->willCorrelationData = "qq";
// $pkt->willUserProperties = array(array("x", "y"));
// $pkt->userProperties = array(array("g", "h"), array("i", "j"));
$bin = $pkt->encode();
print "\n >>> Sending: " . bin2hex($bin) . "\n";
print \PhpMqtt\Protocol\PacketInspector::packetDebugString($pkt) . "\n\n";
fwrite($sock, $bin);




$data = fread($sock, 8192);
print "\n <<< Received: " . bin2hex($data) . "\n";
$pkt = \PhpMqtt\Protocol\v5\Packet::decode($data);
print \PhpMqtt\Protocol\PacketInspector::packetDebugString($pkt) . "\n\n";





$pkt = new \PhpMqtt\Protocol\v5\Packet_PUBLISH();
$pkt->topic = "pptest/ciao";
// $pkt->dup = true;
// $pkt->qos = 2;
// $pkt->retain = true;
// $pkt->packetIdentifier = 0x1122;
// $pkt->messageFormat = 1;
// $pkt->messageExpiration = 0x31323334;
$pkt->topicAlias = 2;
// $pkt->responseTopic = "pippo";
// $pkt->correlationData = "pluto";
// $pkt->userProperties = array(array("a", "b"));
// $pkt->subscriptionIdentifiers = array(0x41, 0x42);
// $pkt->contentType = "text/html";
$pkt->content = "ababababa";
$bin = $pkt->encode();
print "\n >>> Sending: " . bin2hex($bin) . "\n";
print \PhpMqtt\Protocol\PacketInspector::packetDebugString($pkt) . "\n\n";
fwrite($sock, $bin);


sleep(1);



$pkt = new \PhpMqtt\Protocol\v5\Packet_PUBLISH();
// $pkt->topic = "pptest/ciao";
// $pkt->dup = true;
$pkt->qos = 1;
// $pkt->retain = true;
$pkt->packetIdentifier = 0x1122;
// $pkt->messageFormat = 1;
// $pkt->messageExpiration = 0x31323334;
$pkt->topicAlias = 2;
// $pkt->responseTopic = "pippo";
// $pkt->correlationData = "pluto";
// $pkt->userProperties = array(array("a", "b"));
// $pkt->subscriptionIdentifiers = array(0x41, 0x42);
// $pkt->contentType = "text/html";
$pkt->content = "cdcdcdcd";
$bin = $pkt->encode();
print "\n >>> Sending: " . bin2hex($bin) . "\n";
print \PhpMqtt\Protocol\PacketInspector::packetDebugString($pkt) . "\n\n";
fwrite($sock, $bin);


sleep(1);



$data = fread($sock, 8192);
print "\n <<< Received: " . bin2hex($data) . "\n";
$pkt = \PhpMqtt\Protocol\v5\Packet::decode($data);
print \PhpMqtt\Protocol\PacketInspector::packetDebugString($pkt) . "\n\n";


sleep(1);



$pkt = new \PhpMqtt\Protocol\v5\Packet_PINGREQ();
$bin = $pkt->encode();
print "\n >>> Sending: " . bin2hex($bin) . "\n";
print \PhpMqtt\Protocol\PacketInspector::packetDebugString($pkt) . "\n\n";
fwrite($sock, $bin);


$data = fread($sock, 8192);
print "\n <<< Received: " . bin2hex($data) . "\n";
$pkt = \PhpMqtt\Protocol\v5\Packet::decode($data);
print \PhpMqtt\Protocol\PacketInspector::packetDebugString($pkt) . "\n\n";



print "\nDone?\n\n";

$data = fread($sock, 8192);
print "\n <<< Received: " . bin2hex($data) . "\n";
$pkt = \PhpMqtt\Protocol\v5\Packet::decode($data);
print \PhpMqtt\Protocol\PacketInspector::packetDebugString($pkt) . "\n\n";


