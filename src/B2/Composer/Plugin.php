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
//		echo "activate plugin\n";
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
		$locker = $composer->getLocker();

		$io->write('<info>BORS© update process</info>');

		$lock_data = $locker->getLockData();
		$all_packages = isset($lock_data['packages']) ? $lock_data['packages'] : array();

		foreach($all_packages as $package)
		{
			$extra = isset($package['extra']) ? $package['extra'] : array();
			if(isset($extra['bors-calls']))
			{
				foreach($extra['bors-calls'] as $callback => $data)
				{
					if(preg_match('/(\w+)::(\w+)$/', $callback, $m))
						$callback = array($m[1], $m[2]);

//					if(is_callable($callback))
						call_user_func($callback, $data, $package, $composer);
//					else
//						$io->write('<error>Incorrect callback: '.print_r($callback, true).'</error>');
				}
			}
		}
	}
}
