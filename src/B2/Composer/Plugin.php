<?php

namespace B2\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class Plugin implements PluginInterface
{
	public function supports($packageType)
	{
		echo "test '$packageType'\n";
		return 'bors-component' === $packageType;
	}

    public static function postAutoloadDump(Event $event)
    {
        $composer = $event->getComposer();
        $io = $event->getIO();
        $io->write('<info>Test: postAutoloadDump</info>');
	}

public function activate(Composer $composer, IOInterface $io)
    {
    	echo "activate plugin\n";
//        $installer = new TemplateInstaller($io, $composer);
//        $composer->getInstallationManager()->addInstaller($installer);
    }
}
