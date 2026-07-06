<?php

$cacheFile = __DIR__ . "/scopus_cache.html";
$cacheTime = 24 * 60 * 60; // 24 hours

// Use cache if it exists and is less than 24 h old, or if query has been updated
$queryFile = __DIR__ . "/scopus_query.txt";

$cacheNeedsUpdate =
    !file_exists($cacheFile) ||
    filemtime($cacheFile) < filemtime($queryFile) ||
    (time() - filemtime($cacheFile) > $cacheTime);

if (!$cacheNeedsUpdate) {
    readfile($cacheFile);
    exit;
}

$apiKey = trim(file_get_contents(__DIR__ . "/scopus_key.txt"));
$query = trim(file_get_contents($queryFile));

$url = "https://api.elsevier.com/content/search/scopus?"
     . "query=" . urlencode($query)
     . "&count=10"
     . "&sort=-coverDate"
     . "&httpAccept=application/json";

$options = [
    "http" => [
        "method" => "GET",
        "header" =>
            "X-ELS-APIKey: $apiKey\r\n" .
            "Accept: application/json\r\n"
    ]
];

$context = stream_context_create($options);
$json = file_get_contents($url, false, $context);

if ($json === false) {
    if (file_exists($cacheFile)) {
        readfile($cacheFile);
        exit;
    }
    die("Scopus API request failed.");
}

$data = json_decode($json, true);
$entries = $data["search-results"]["entry"] ?? [];

ob_start();

echo "<h2>Latest FinTomo-related Scopus publications</h2>";
echo "<ol>";

foreach ($entries as $entry) {
    $title   = $entry["dc:title"] ?? "No title";
    $authors = $entry["dc:creator"] ?? "Unknown authors";
    $journal = $entry["prism:publicationName"] ?? "";
    $year    = substr($entry["prism:coverDate"] ?? "", 0, 4);
    $doi     = $entry["prism:doi"] ?? "";
    $link    = $doi ? "https://doi.org/" . $doi : ($entry["prism:url"] ?? "#");

    echo "<li>";
    echo "<strong>" . htmlspecialchars($title) . "</strong><br>";
    echo htmlspecialchars($authors);
    if ($journal) echo " et al., <em>" . htmlspecialchars($journal) . "</em>";
    if ($year) echo " (" . htmlspecialchars($year) . ")";
    if ($doi) {
        echo ". DOI: <a href='" . htmlspecialchars($link) . "' target='_blank' rel='noopener'>"
           . htmlspecialchars($doi)
           . "</a>";
    }
    echo "</li><br>";
}

echo "</ol>";

$html = ob_get_clean();

file_put_contents($cacheFile, $html);

echo $html;
?>