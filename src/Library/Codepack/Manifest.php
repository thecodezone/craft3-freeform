<?php
/**
 * Freeform for Craft
 *
 * @package       Solspace:Freeform
 * @author        Solspace, Inc.
 * @copyright     Copyright (c) 2008-2020, Solspace, Inc.
 * @link          https://docs.solspace.com/craft/freeform
 * @license       https://docs.solspace.com/license-agreement
 */

namespace Solspace\Freeform\Library\Codepack;

use craft\helpers\StringHelper;
use Solspace\Freeform\Library\Codepack\Exceptions\Manifest\ManifestException;
use Solspace\Freeform\Library\Codepack\Exceptions\Manifest\ManifestNotPresentException;
use Symfony\Component\Filesystem\Filesystem;

class Manifest
{
    /** @var string */
    private $packageName;

    /** @var string */
    private $packageDesc;

    /** @var string */
    private $vendor;

    /** @var string */
    private $vendorUrl;

    /** @var string */
    private $docsUrl;

    private static $availableProperties = array(
        'package_name',
        'package_desc',
        'package_version',
        'vendor',
        'vendor_url',
        'docs_url',
    );

    private static $requiredProperties = array(
        'package_name',
        'package_version',
        'vendor',
    );

    /**
     * @param string $manifestPath
     */
    public function __construct($manifestPath)
    {
        $this->parseManifestFile($manifestPath);
    }

    /**
     * @return string
     */
    public function getPackageName(): string
    {
        return $this->packageName;
    }

    /**
     * @return string
     */
    public function getPackageDesc(): string
    {
        return $this->packageDesc;
    }

    /**
     * @return string
     */
    public function getVendor(): string
    {
        return $this->vendor;
    }

    /**
     * @return string
     */
    public function getVendorUrl(): string
    {
        return $this->vendorUrl;
    }

    /**
     * @return string
     */
    public function getDocsUrl(): string
    {
        return $this->docsUrl;
    }

    /**
     * @param string $manifestPath
     *
     * @throws ManifestException
     */
    private function parseManifestFile(string $manifestPath)
    {
        $fs = new Filesystem();

        if (!$fs->exists($manifestPath)) {
            throw new ManifestNotPresentException(
                sprintf('Manifest file is not present in %s', $manifestPath)
            );
        }

        $content = file_get_contents($manifestPath);
        $data    = json_decode($content, true);

        foreach (self::$availableProperties as $property) {
            if (in_array($property, self::$requiredProperties, true)) {
                if (!isset($data[$property])) {
                    throw new ManifestException(
                        sprintf('Mandatory "%s" property not defined in manifest.json', $property)
                    );
                }

                if (empty($data[$property])) {
                    throw new ManifestException(
                        sprintf('Mandatory "%s" property is empty in manifest.json', $property)
                    );
                }
            }

            $camelCasedProperty = StringHelper::toCamelCase($property);
            if (property_exists($this, $camelCasedProperty)) {
                $this->{$camelCasedProperty} = $data[$property];
            }
        }
    }
}
