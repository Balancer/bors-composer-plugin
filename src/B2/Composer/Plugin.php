<?php

namespace B2\Composer;

use Composer\Installer\LibraryInstaller;

class Plugin extends LibraryInstaller
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
}
