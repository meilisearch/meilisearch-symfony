<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with Meilisearch\\\\Contracts\\\\Task will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/src/Command/MeilisearchCreateCommand.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to static method getClass\\(\\) on an unknown class Doctrine\\\\Common\\\\Util\\\\ClassUtils\\.$#',
	'identifier' => 'class.notFound',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/MeilisearchManager.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to static method getClass\\(\\) on an unknown class Doctrine\\\\Common\\\\Util\\\\ClassUtils\\.$#',
	'identifier' => 'class.notFound',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/MeilisearchService.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'hits\' on array\\{hits\\: array\\<int, mixed\\>, query\\: string, processingTimeMs\\: int, limit\\: int, offset\\: int, estimatedTotalHits\\: int, requestUid\\: non\\-empty\\-string, nbHits\\: int\\} in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.offset',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/MeilisearchService.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
