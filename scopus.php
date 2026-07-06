<?php

$cacheFile = __DIR__ . "/scopus_cache.html";
$queryFile = __DIR__ . "/scopus_query.txt";

$apiKey = getenv("SCOPUS_KEY");

if (!$apiKey) {
    die("SCOPUS_KEY not found.");
}

$query = trim(file_get_contents($queryFile));

$scopusSearchUrl = "https://www.scopus.com/results/results.uri?src=s&s="
                 . urlencode($query);

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

date_default_timezone_set("Europe/Helsinki");

echo "<p style='font-size:0.9em;color:#666;'>
Generated automatically from Scopus on "
   . date("j M Y, H:i")
   . " (Finnish time).
</p>";

echo "<p style='font-size:0.9em;'>
<a href='" . htmlspecialchars($scopusSearchUrl) . "' target='_blank' rel='noopener'>
View all matching results in Scopus
</a>
</p>";

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
