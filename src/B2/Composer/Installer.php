<?php

namespace B2\Composer;

use Composer\Installer\LibraryInstaller;

class Installer extends LibraryInstaller
{
	public function supports($packageType)
	{
		echo "test installer '$packageType'\n";
		return 'bors-component' === $packageType;
	}

    public static function postAutoloadDump(Event $event)
    {
        $composer = $event->getComposer();
        $io = $event->getIO();
        $io->write('<info>Test: postAutoloadDump</info>');
	}
}
