<?php
// bootstrap.php
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;

require_once "vendor/autoload.php";

$proxyDirectory = __DIR__ . '/../proxies/';

$config = new Configuration();

$annotationDriver = $config->newDefaultAnnotationDriver(__DIR__."/src/Entities", true);

$config->setMetadataDriverImpl($annotationDriver);
$config->setAutoGenerateProxyClasses(AbstractProxyFactory::AUTOGENERATE_ALWAYS);
$config->setProxyDir($proxyDirectory);
$config->setProxyNamespace('Greydnls\Workshop\Proxies');

$conn = [
    'driver' => 'mysqli',
    'user' => 'root',
    'password' => '',
    'host' => 'localhost',
    'dbname' => 'workshop',
];

// obtaining the entity manager
$entityManager = EntityManager::create($conn, $config);