<?php

namespace DreamFactory\Tools\Composer\Installer;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;

class Installer extends LibraryInstaller
{
    /**
     * Package types to installer class map
     *
     * @var array
     */
    private array $supportedTypes = [
        'dreamfactory' => 'DreamFactoryInstaller',
    ];

    /** {@inheritDoc} */
    public function getInstallPath( PackageInterface $package ): string
    {
        $type = $package->getType();
        $frameworkType = $this->findFrameworkType( $type );

        if ( $frameworkType === false )
        {
            throw new \InvalidArgumentException(
                'Sorry the package type of this package is not yet supported.'
            );
        }

        $class = __NAMESPACE__ . '\\' . $this->supportedTypes[$frameworkType];

        /** @type BaseInstaller $installer */
        $installer = new $class( $package, $this->composer );

        return $installer->getInstallPath( $package, $frameworkType );
    }

    /** {@inheritDoc} */
    public function uninstall( InstalledRepositoryInterface $repo, PackageInterface $package ): void
    {
        if ( !$repo->hasPackage( $package ) )
        {
            throw new \InvalidArgumentException( 'Package is not installed: ' . $package );
        }

        $repo->removePackage( $package );

        $installPath = $this->getInstallPath( $package );
        $this->io->write(
            sprintf(
                'Deleting %s - %s',
                $installPath,
                $this->filesystem->removeDirectory( $installPath ) ? '<comment>deleted</comment>' : '<error>not deleted</error>'
            )
        );
    }

    /** {@inheritDoc} */
    public function supports( $packageType ): bool
    {
        $frameworkType = $this->findFrameworkType( $packageType );

        if ( $frameworkType === false )
        {
            return false;
        }

        $locationPattern = $this->getLocationPattern( $frameworkType );

        return preg_match( '#' . $frameworkType . '-' . $locationPattern . '#', $packageType, $matches ) === 1;
    }

    /**
     * Finds a supported framework type if it exists and returns it
     *
     * @param string $type
     *
     * @return bool|string
     */
    protected function findFrameworkType( $type ): bool|string
    {
        $frameworkType = false;

        krsort( $this->supportedTypes );

        foreach ( $this->supportedTypes as $key => $val )
        {
            if (str_starts_with($type, $key))
            {
                $frameworkType = substr( $type, 0, strlen( $key ) );
                break;
            }
        }

        return $frameworkType;
    }

    /**
     * Get the second part of the regular expression to check for support of a
     * package type
     *
     * @param string $frameworkType
     *
     * @return bool|string
     */
    protected function getLocationPattern( $frameworkType ): bool|string
    {
        $pattern = false;
        if ( !empty( $this->supportedTypes[$frameworkType] ) )
        {
            $frameworkClass = __NAMESPACE__ . '\\' . $this->supportedTypes[$frameworkType];
            /** @var BaseInstaller $framework */
            $framework = new $frameworkClass( null, $this->composer );
            $locations = array_keys( $framework->getLocations() );
            $pattern = $locations ? '(' . implode( '|', $locations ) . ')' : false;
        }

        return $pattern ?: '(\w+)';
    }
}
