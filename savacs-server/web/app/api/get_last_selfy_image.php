<?php

declare(strict_types=1);

require_once('../lib.php');

function main()
{
    $password = null;
    $cpuSerialNumber = null;

    try {
        $password = ApacheEnvironmentWrapper::getPasswordStringByParams(
            $_POST,
            'password'
        );

        $cpuSerialNumber = ApacheEnvironmentWrapper::getCpuSerialNumberByParams(
            $_POST,
            'cpuSerialNumber'
        );
    } catch (OutOfBoundsException $e) {
        BasicTools::writeErrorLogAndDie(
            'OutOfBoundsException: ' .
            $e->getMessage()
        );
    } catch (UnexpectedValueException $e) {
        BasicTools::writeErrorLogAndDie(
            'UnexpectedValueException: ' .
            $e->getMessage()
        );
    }

    $pdo = null;

    try {
        $pdo = DBCommon::createConnection();
    } catch (PDOException $e) {
        BasicTools::writeErrorLogAndDie(
            'PDOException in createConnection: ' .
            $e->getMessage()
        );
    }

    $toPhotostandId = null;

    try {
        $toPhotostandId = DBCPhotostand::getIdByCpuSerialNumberAndPassword(
            $pdo,
            $cpuSerialNumber,
            $password
        );
    } catch (RuntimeException $e) {
        BasicTools::writeErrorLogAndDie(
            'RuntimeException in photostandA Authorization: ' .
            $e->getMessage()
        );
    } catch (RangeException $e) {
        BasicTools::writeErrorLogAndDie(
            'RangeException in photostandA Authorization: ' .
            $e->getMessage()
        );
    }

    $fromPhotostandIds = DBCPhotostand::getActiveAssociations(
        $pdo,
        $toPhotostandId
    );

    $lastSelfyImageArray = array();

    foreach ($fromPhotostandIds as $fromPhotostandId) {
        $lastSelfyImage = null;

        try {
            $lastSelfyImage = DBCSelfyImage::getLatestImage(
                $pdo,
                $fromPhotostandId,
                $toPhotostandId
            );
        } catch (PDOException $e) {
            // Does this work?
            throw $e;
        } catch (RuntimeException $e) {
            $lastSelfyImageArray[strval($fromPhotostandId)] = array(
                'status' => 0
            );

            continue;
        }

        $selfyImageUri =
            (ContentsDirectoryPaths::getSelfyImages())->getWebServerPathWithoutPrefix() .
            $lastSelfyImage->getFileName();

        $lastSelfyImageArray[strval($fromPhotostandId)] = array(
            'status'        => 1,
            'created_at'    => $lastSelfyImage->getCreatedAt(),
            'uri'           => $selfyImageUri
        );
    }

    $jsonString = json_encode($lastSelfyImageArray);
    assert(!($jsonString === false), 'json_encode fail.');

    header('Content-type: application/json');

    echo $jsonString;
}

main();

