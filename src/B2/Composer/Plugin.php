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
	public function supports($packageType)
	{
		echo "test plugin support '$packageType'\n";
		return 'bors-component' === $packageType;
	}

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
		echo "plugin: getSubscribedEvents\n";
		return array(
			ScriptEvents::POST_AUTOLOAD_DUMP => array(
//				array('onPostAutoloadDump1', 0),
				array('onPostAutoloadDump', 0)
//				array('onPostAutoloadDump3', 30)
			),
		);
	}

	public static function postAutoloadDump(CommandEvent $event)
	{
		$composer = $event->getComposer();
		$io = $event->getIO();
		$io->write('<info>Test: postAutoloadDump</info>');
	}

	public static function postAutoloadDump1(CommandEvent $event)
	{
		$composer = $event->getComposer();
		$io = $event->getIO();
		$io->write('<info>Test: postAutoloadDump1</info>');
	}

	public static function postAutoloadDump3(CommandEvent $event)
	{
		$composer = $event->getComposer();
		$io = $event->getIO();
		$io->write('<info>Test: postAutoloadDump3</info>');
	}
}
