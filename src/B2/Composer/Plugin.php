<?php

namespace B2\Composer;

class Plugin implements Composer\Plugin\PluginInterface
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
