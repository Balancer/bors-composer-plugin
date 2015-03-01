<?php

class b2_composer extends Composer\Installer\LibraryInstaller
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
