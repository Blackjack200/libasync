<?php
declare(strict_types=1);

use libasync\exception\ExecutionExceptionWrapper;
use libasync\runtime\AsyncExecutionEnvironment;
use Opis\Closure\SerializableClosure;
use pocketmine\thread\ThreadSafeClassLoader;
use pocketmine\utils\Utils;

ob_start();

if ($argc < 3) {
	die("No closure data provided.");
}

require_once __DIR__ . "/../../../vendor/autoload.php";

try {
	$autoloader = json_decode(base64_decode($argv[1]), true, 512, JSON_THROW_ON_ERROR);
	if (!is_array($autoloader) && count($autoloader) !== 3) {
		throw new RuntimeException("Unable to decode autoloader.");
	}
	[$lookup, $psr4, $composer] = $autoloader;

	foreach ($composer as $autoload) {
		if (!file_exists($autoload)) {
			throw new RuntimeException("Autoload file not found: " . $autoload);
		}
		require_once $autoload;
	}

	$loader = new ThreadSafeClassLoader();
	foreach ($lookup as $item) {
		if (!is_dir($item)) {
			throw new RuntimeException("Lookup path not found: " . $item);
		}
		$loader->addPath('', $item);
	}

	foreach ($psr4 as $k => $v) {
		foreach ($v as $vv) {
			if (!is_dir($vv)) {
				throw new RuntimeException("PSR-4 path not found: " . $vv);
			}
			$loader->addPath($k, $vv);
		}
	}

	$loader->register();

	$closure = igbinary_unserialize(Utils::assumeNotFalse(base64_decode($argv[2]), "Invalid closure data provided."));
	if (!$closure instanceof SerializableClosure) {
		throw new RuntimeException("Invalid closure deserialized.");
	}

	if ($argv[3] !== 'null') {
		$serializedEnv = Utils::assumeNotFalse(base64_decode($argv[3]), "Invalid closure data provided.");

		$env = $serializedEnv ? igbinary_unserialize($serializedEnv) : null;
		if ($env && !is_array($env) || count($env) !== 2) {
			throw new RuntimeException("Invalid environment data.");
		}
	} else {
		$env = null;
	}


	if (!defined('bootstrap\PRODUCTION')) {
		define("bootstrap\PRODUCTION", false);
	}

	$env = new AsyncExecutionEnvironment($env[0]->getClosure(), $env[1]->getClosure());

	$result = $env->run($closure->getClosure());

	ob_end_clean();
	echo igbinary_serialize([true, $result]);

} catch (Throwable $err) {
	ob_end_clean();
	$error = (ExecutionExceptionWrapper::wrap($err));
	echo igbinary_serialize([false, $error]);
	exit(1);
}