<?php

namespace B2\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

class Plugin implements PluginInterface, EventSubscriberInterface
{
	static $bors_data = [];


	public function activate(Composer $composer, IOInterface $io)
	{
		$this->composer = $composer;
		$this->io = $io;

		if(!defined('COMPOSER_ROOT'))
			// Ад. Но сходу не нашёл, как получить baseDir в Composer.
			define('COMPOSER_ROOT', dirname(dirname(dirname(__DIR__))));
	}

	public static function getSubscribedEvents()
	{
		return array(
			ScriptEvents::POST_AUTOLOAD_DUMP => array(
				array('postAutoloadDump', 0), // Приоритет, чем выше, тем раньше вызывается.
			),
		);
	}

	public static function postAutoloadDump(Event $event)
	{
		$composer = $event->getComposer();
		$io = $event->getIO();
		$locker = $composer->getLocker();

		$io->write('<info>BORS© update process</info>');

		$lock_data = $locker->getLockData();
		$all_packages = isset($lock_data['packages']) ? $lock_data['packages'] : [];

		$all_packages[] = json_decode(file_get_contents(COMPOSER_ROOT.'/composer.json'), true);

		// Проверяем на наличие autoload.php, а то можем грузиться из .phar
		if(file_exists($d = __DIR__.'/../../autoload.php'))
			// Иначе новые классы не хотят грузиться :-/
			require $d;

		$data_key_names = [];

		foreach($all_packages as $package)
		{
			$extra = isset($package['extra']) ? $package['extra'] : array();

			if(!empty($package['name']))
			{
				$package_path = COMPOSER_ROOT. '/vendor/' . $package['name'];
				// So detecting root package, not in vendor dir.
				if(!file_exists($package_path.'/composer.json'))
					$package_path = COMPOSER_ROOT;
			}
			else
				$package_path = COMPOSER_ROOT;

			if(isset($extra['bors-patches']))
				foreach($extra['bors-patches'] as $patch_package_name => $patches)
					self::doPatches($package, $patch_package_name, $patches, $io, $all_packages);

			if(isset($extra['bors-calls']))
			{
				foreach($extra['bors-calls'] as $callback => $data)
				{
					if(is_callable($callback))
						call_user_func($callback, $data, $composer, $package);
					else
						$io->write('<error>Incorrect callback: '.print_r($callback, true).'</error>');
				}
			}

			self::append_extra($package_path, $extra, 'classes');
			self::append_extra($package_path, $extra, 'route-maps');
			self::append_extra($package_path, $extra, 'templates');
			self::append_extra($package_path, $extra, 'smarty-plugins');
			self::append_extra($package_path, $extra, 'lcml-dir');
			self::append_extra($package_path, $extra, 'webroot');
			self::append_extra($package_path, $extra, 'autoroute-prefixes', false);
			self::append_extra($package_path, $extra, 'route-static', false);
			self::append_extra($package_path, $extra, 'register-app', false);
			self::append_extra($package_path, $extra, 'register-view', false);
			self::append_extra_data($package_path, $extra, 'data');

			if(!empty($extra['bors-app']))
			{
				\B2\Composer\Cache::appendData('config/packages/apps',  [ $package['name'] => $extra['bors-app']]);
				\B2\Composer\Cache::appendData('config/packages/path', [ $package['name'] => $package_path]);
				\B2\Composer\Cache::appendData('config/packages/names', [ $extra['bors-app'] => $package['name']]);
				\B2\Composer\Cache::appendData('config/packages/app-path', [ $extra['bors-app'] => $package_path]);

				if(!empty($extra['bors-routers']))
					\B2\Composer\Cache::appendData('config/apps/routers',  [ $extra['bors-app'] => $extra['bors-routers']]);
			}

			if(!empty($extra['bors-route-map']))
				self::append_extra($package_path, $extra, 'route-map', true, 'packages', $package['name']);
//				\B2\Composer\Cache::appendData('config/packages/route-map', [ $package['name'] => $extra['bors-route-map']]);

			foreach($extra as $key => $val)
			{
				if(!preg_match('/^bors-data-(.+)$/', $key, $m))
					continue;

				self::append_extra($package_path, $extra, $m[1], false);
				\B2\Composer\Cache::appendData('config/data/'.$m[1], $val);
				$data_key_names[$m[1]] = true;
			}
		}

		$code = "if(!defined('COMPOSER_ROOT'))\n\tdefine('COMPOSER_ROOT', dirname(dirname(__DIR__)));\n\n";

		$code .= "bors::\$composer_class_dirs = [\n\t".join(",\n\t", array_unique(\B2\Composer\Cache::getData('config/dirs/classes', [])))."];\n";
		$code .= "bors::\$composer_template_dirs = [\n\t".join(",\n\t", array_unique(\B2\Composer\Cache::getData('config/dirs/templates', [])))."];\n";
		$code .= "bors::\$composer_smarty_plugin_dirs = [\n\t".join(",\n\t", array_unique(\B2\Composer\Cache::getData('config/dirs/smarty-plugins', [])))."];\n";
		$code .= "bors::\$composer_lcml_dir = [\n\t".join(",\n\t", array_unique(\B2\Composer\Cache::getData('config/dirs/lcml-dir', [])))."];\n";
		$code .= "bors::\$composer_webroots = [\n\t".join(",\n\t", array_unique(\B2\Composer\Cache::getData('config/dirs/webroot', [])))."];\n";
		$code .= "bors::\$composer_route_maps = [\n\t".join(",\n\t", array_unique(\B2\Composer\Cache::getData('config/dirs/route-maps', [])))."];\n";

		\B2\Composer\Cache::addAutoload('config/dirs', $code);

		$code = "if(!defined('COMPOSER_ROOT'))\n\tdefine('COMPOSER_ROOT', dirname(dirname(__DIR__)));\n\n";

		$code .= "bors::\$package_apps = [\n";
		foreach(\B2\Composer\Cache::getData('config/packages/apps', []) as $pkg => $app)
			$code .= "\t'$pkg' => '".addslashes($app)."',\n";
		$code .= "];\n";

		$code .= "bors::\$package_route_maps = [\n";
		foreach(\B2\Composer\Cache::getData('config/packages/route-map', []) as $pkg => $route_map)
			$code .= "\t'$pkg' => ".$route_map.",\n";
		$code .= "];\n";

		$code .= "\nbors::\$package_path = [\n";
		foreach(\B2\Composer\Cache::getData('config/packages/path', []) as $pkg => $path)
			$code .= "\t'$pkg' => ".self::make_path($path).",\n";
		$code .= "];\n";

		\B2\Composer\Cache::addAutoload('config/packages', $code);

		$code = "if(!defined('COMPOSER_ROOT'))\n\tdefine('COMPOSER_ROOT', dirname(dirname(__DIR__)));\n\n";

		$code .= "bors::\$composer_autoroute_prefixes = [\n\t".join(",\n\t", array_unique(\B2\Composer\Cache::getData('config/dirs/autoroute-prefixes', [])))."];\n\n";

		$code .= "bors::\$app_routers = [\n";
		foreach(\B2\Composer\Cache::getData('config/apps/routers', []) as $pkg => $routers)
			$code .= "\t'$pkg' => ".var_export($routers, true).",\n";
		$code .= "];\n";

		$code .= "\nbors::\$package_names = [\n";
		foreach(\B2\Composer\Cache::getData('config/packages/names', []) as $app => $pkg)
			$code .= "\t'".addslashes($app)."' => '".addslashes($pkg)."',\n";
		$code .= "];\n";

		$code .= "\nbors::\$package_app_path = [\n";
		foreach(\B2\Composer\Cache::getData('config/packages/app-path', []) as $app => $path)
			$code .= "\t'".addslashes($app)."' => ".self::make_path($path).",\n";
		$code .= "];\n";

		$code .= "bors::\$composer_route_static     = [\n\t".join(",\n\t", array_unique(\B2\Composer\Cache::getData('config/dirs/route-static', [])))."];\n";
		$code .= "bors::\$composer_register_in_app  = [\n\t".join(",\n\t", array_unique(\B2\Composer\Cache::getData('config/dirs/register-app', [])))."];\n";
		$code .= "bors::\$composer_register_in_view = [\n\t".join(",\n\t", array_unique(\B2\Composer\Cache::getData('config/dirs/register-view', [])))."];\n";

		if(!empty(self::$bors_data['data']))
			$code .= "bors::\$composer_data = ".var_export(self::$bors_data['data'], true).";\n";

		foreach(array_keys($data_key_names) as $name)
			$code .= "bors::\$composer_extra_".str_replace('-', '_', $name)." = ".var_export(\B2\Composer\Cache::getData('config/data/'.$name, []), true).";\n";

		\B2\Composer\Cache::addAutoload('config/apps', $code);
	}

	static function make_path($path)
	{
		$path = str_replace(dirname(dirname(dirname(__DIR__))), '', $path);
		return $path ? "COMPOSER_ROOT.'".addslashes($path)."'" : 'COMPOSER_ROOT';
	}

	static function append_extra($package_path, $extra, $name, $with_path = true, $type='dirs', $package_name = NULL)
	{
		$package_path = str_replace(dirname(dirname(dirname(__DIR__))), '', $package_path);

		if(empty($extra['bors-'.$name]))
			return;

		$dirs = $extra['bors-'.$name];

		$param_name = 'config/'.$type.'/'.$name;

		if($with_path)
		{
			//WTF? Where now /vendor prefix in path? o_O
			if(!preg_match('!/vendor/!', $package_path))
				$package_path = '/vendor'.$package_path;

			if(is_array($dirs))
			{
				foreach($dirs as $x)
					\B2\Composer\Cache::appendData($param_name, "COMPOSER_ROOT.'$package_path/$x'");
			}
			else
			{
				if($package_name)
					\B2\Composer\Cache::appendData($param_name, [$package_name => "COMPOSER_ROOT.'$package_path/$dirs'"]);
				else
					\B2\Composer\Cache::appendData($param_name, "COMPOSER_ROOT.'$package_path/$dirs'");
			}
		}
		else
		{
			if(is_array($dirs))
			{
				foreach($dirs as $key => $x)
				{
					if(is_numeric($key))
						\B2\Composer\Cache::appendData($param_name, "'".addslashes($x)."'");
					elseif(is_array($x))
						\B2\Composer\Cache::appendData($param_name, "'".addslashes($key)."' => ".var_export($x, true));
					else
						\B2\Composer\Cache::appendData($param_name, "'".addslashes($key)."' => '".addslashes($x)."'");
				}
			}
			else
				\B2\Composer\Cache::appendData($param_name, "'".addslashes($dirs)."'");
		}
	}

	static function doPatches($package_with_patch, $package_name, $patches, $io, $all_packages)
	{
		$io->write("<info>Do patches $package_name: ".print_r($patches, true).'</info>');

		$package_path = NULL;

		foreach($all_packages as $package)
		{
			if($package['name'] == $package_name)
			{
				$package_path = COMPOSER_ROOT. '/vendor/' . $package['name'];
				break;
			}
		}

		if(!is_array($patches))
			$patches = [$patches];

		$executor = new \Composer\Util\ProcessExecutor($io);

		foreach($patches as $title => $patch_file)
		{
			if(is_numeric($title))
				$title = $patch_file;

			$patch_file = COMPOSER_ROOT. '/vendor/' . $package_with_patch['name'] . DIRECTORY_SEPARATOR . $patch_file;

			if(!file_exists($patch_file))
			{
				$io->write("<error>Can't find file path $patch_file for package $package_name</error>");
				continue;
			}

			foreach(['-p1', '-p0', '-p2'] as $patch_level)
			{
				// --no-backup-if-mismatch here is a hack that fixes some
				// differences between how patch works on windows and unix.
				if($patched = self::executeCommand($io, "patch %s --no-backup-if-mismatch -d %s < %s", $patch_level, $package_path, $patch_file))
		 			 break;
			}
	  	}
	}

	// Inspired from cweagans/composer-patches
	static private function executeCommand($io, $cmd)
	{
		$executor = new \Composer\Util\ProcessExecutor($io);

		// Shell-escape all arguments except the command.
		$args = func_get_args();

		foreach($args as $index => $arg)
			if ($index > 1)
				$args[$index] = escapeshellarg($arg);

		// And replace the arguments.
		$command = call_user_func_array('sprintf', array_slice($args, 1));
		$output = '';
		if($io->isVerbose())
		{
			$io->write('<comment>' . $command . '</comment>');
			$output = function($type, $data) use ($io)
			{
				if($type == \Symfony\Component\Process\Process::ERR)
					$io->write('<error>' . $data . '</error>');
				else
					$io->write('<comment>' . $data . '</comment>');
			};
		}

		return ($executor->execute($command, $output) == 0);
	}

	static function append_extra_data($package_path, $extra, $name)
	{
		$package_path = str_replace(dirname(dirname(dirname(__DIR__))), '', $package_path);

		if(empty($extra['bors-'.$name]))
			return;

		$data = $extra['bors-'.$name];

		if(is_array($data))
		{
			foreach($data as $key => $x)
			{
				if(is_numeric($key))
				{
					self::$bors_data[$name][] = $x;
				}
				else
				{
					if(empty(self::$bors_data[$name][$key]))
						self::$bors_data[$name][$key] = [];

					if(!is_array($x))
						$x = [$x];

					self::$bors_data[$name][$key] = array_merge(self::$bors_data[$name][$key], $x);
				}
			}
		}
		else
			self::$bors_data[$name] = array_merge(self::$bors_data[$name], [$data]);
	}
}
