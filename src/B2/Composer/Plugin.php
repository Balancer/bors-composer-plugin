<?php

namespace B2\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Script\CommandEvent;
use Composer\Script\ScriptEvents;

class Plugin implements PluginInterface, EventSubscriberInterface
{
	public function activate(Composer $composer, IOInterface $io)
	{
		echo "activate plugin\n";
//		$installer = new TemplateInstaller($io, $composer);
//		$composer->getInstallationManager()->addInstaller($installer);
		$this->composer = $composer;
		$this->io = $io;
	}

	public static function getSubscribedEvents()
	{
//		echo "plugin: getSubscribedEvents\n";
		return array(
			ScriptEvents::POST_AUTOLOAD_DUMP => array(
				array('postAutoloadDump', 0), // Приоритет, чем выше, тем раньше вызывается.
			),
		);
	}

	public static function postAutoloadDump(CommandEvent $event)
	{
		$composer = $event->getComposer();
		$io = $event->getIO();
		$io->write('<info>Test: postAutoloadDump</info>');
		echo "args="; print_r($event->getArguments());
		echo "flags="; print_r($event->getFlags());
		echo "name="; print_r($event->getName());
	}
}
