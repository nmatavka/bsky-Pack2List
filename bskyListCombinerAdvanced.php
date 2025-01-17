<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
if(isset($_POST['submit'])) {

    $BSKY_HANDLETEST=str_replace(["@",'bsky.app'],["",'bsky.social'],$_POST['handle']);
    $BSKY_PWTEST=$_POST['apppassword'];
    $packURL=$_POST['targeturl'];
    $listURL1=$_POST['sourceurl1'];
    $listURL2=$_POST['sourceurl2'];
    $operation = $_POST['operation'];
    $invert = isset($_POST['invert']);

    function bsky_List2StarterPack($bsky, $spAT, $listAT1, $listAT2, $operation, $invert) {
        // Read the source lists for accounts to add to the pack list
        $sourceList1 = fetchListItems($bsky, $listAT1);
        $sourceList2 = fetchListItems($bsky, $listAT2);

        $resultList = [];
        switch ($operation) {
            case 'and':
                $resultList = array_intersect($sourceList1, $sourceList2);
                break;
            case 'or':
                $resultList = array_unique(array_merge($sourceList1, $sourceList2));
                break;
            case 'xor':
                $resultList = array_diff(array_merge($sourceList1, $sourceList2), array_intersect($sourceList1, $sourceList2));
                break;
        }

        if ($invert) {
            $allItems = array_unique(array_merge($sourceList1, $sourceList2));
            $resultList = array_diff($allItems, $resultList);
        }

        if ($resultList) {
            foreach ($resultList as $listItem) {
                // Add the user to the pack list
                $args = [
                    'collection' => 'app.bsky.graph.listitem',
                    'repo' => $bsky->getAccountDid(),
                    'record' => [
                        'createdAt' => date('c'),
                        '$type' => 'app.bsky.graph.listitem',
                        'subject' => $listItem,
                        'list' => $spAT,
                    ],
                ];
                $bsky->request('POST', 'com.atproto.repo.createRecord', $args);
            }
        } else {
            echo "No items found for the selected operation.";
        }
    }

    function fetchListItems($bsky, $listAT) {
        $cursor = '';
        $listItems = [];
        do {
            $args = ['list' => $listAT, 'limit' => 100, 'cursor' => $cursor];
            $res = $bsky->request('GET', 'app.bsky.graph.getList', $args);
            $listItems = array_merge($listItems, array_map(fn($item) => $item->subject->did, (array)$res->items));
            $cursor = $res->cursor;
        } while ($cursor);
        return $listItems;
    }

    // The rest of the code remains the same as in bskyListCombiner.php
    require "./bsky-core.php";

    // Run this crap
    // Init the connection
    $pdsURL = getPDS($BSKY_HANDLETEST);
    if ($pdsURL == '') {
        echo 'Invalid Username Entered. Make sure it is the full name, including the domain suffix (e.g. user.bsky.social, not just user).';
    } else {
        $bluesky = new BlueskyApi($BSKY_HANDLETEST, $BSKY_PWTEST, $pdsURL);

        $packURL = deParam($packURL);
        $listURL1 = deParam($listURL1);
        $listURL2 = deParam($listURL2);

        if ($bluesky->hasApiKey()) {
            // Handle short URLs for the pack
            $packURL = str_replace('bsky.app/starter-pack-short', 'go.bsky.app', $packURL);
            if (strpos($packURL, "go.bsky.app") > 0) {
                $packURL = curlGetFullUrl($packURL);
            }

            $arrPack = explode('/', $packURL);
            $userHandle = $arrPack[count($arrPack) - 3];
            $packID = $arrPack[count($arrPack) - 1];

            $arrList1 = explode('/', $listURL1);
            $listUserHandle1 = $arrList1[count($arrList1) - 3];
            $listID1 = $arrList1[count($arrList1) - 1];

            $arrList2 = explode('/', $listURL2);
            $listUserHandle2 = $arrList2[count($arrList2) - 3];
            $listID2 = $arrList2[count($arrList2) - 1];

            $packAT = bskyListATs($bluesky, $userHandle, $packID);
            $listAT1 = bskyListATs($bluesky, $listUserHandle1, $listID1);
            $listAT2 = bskyListATs($bluesky, $listUserHandle2, $listID2);

            if ($packAT != '' && $listAT1 != '' && $listAT2 != '') {
                // Came back with an at: URI, so I can now fetch the Starter Pack and parse for the list details inside
                bsky_List2StarterPack($bluesky, $packAT, $listAT1, $listAT2, $operation, $invert);
            } else {
                echo "Could not find the specified lists. Please check the URLs and try again.";
            }

            $bluesky = null;
            echo '<p>Import Complete</p><p>Check your <a href="https://bsky.app/lists" target="_blank">User Lists</a> for more details.';
        } else {
            echo "Error connecting to your account. Please check the username and app password and try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Import members from two lists into another, existing list</title>
</head>
<body>
    <h1>Import members from two lists into another, existing list</h1>
    <?php
    require "./app-pw.php";
    ?>
    <form action="" method="POST">
        <p>Your BSky Handle: <input type="text" name="handle" placeholder="user.bsky.social" required></p>
        <p>Your BSky <a href="https://bsky.app/settings/app-passwords" target="_blank">App Password</a>: <input type="password" name="apppassword" placeholder="abcd-1234-fghi-5678" required></p>
        <p>Source List 1 URL: <input type="text" name="sourceurl1" placeholder="https://bsky.app/profile/wandrme.paxex.aero/lists/3jzxvt5ms372z" required></p>
        <p>Source List 2 URL: <input type="text" name="sourceurl2" placeholder="https://bsky.app/profile/wandrme.paxex.aero/lists/3jzxvt5ms372z" required></p>
        <p>Target List URL: <input type="text" name="targeturl" placeholder="https://bsky.app/profile/wandrme.paxex.aero/lists/3l6stg6xfrc23" required></p>
        <p>Operation:</p>
        <p>
            <input type="radio" name="operation" value="and" checked> AND
            <input type="radio" name="operation" value="or"> OR
            <input type="radio" name="operation" value="xor"> XOR
        </p>
        <p>
            <input type="checkbox" name="invert"> Invert (NOT)
        </p>
        <input type="submit" name="submit" value="Submit">
    </form>
    <?php
    include "./footer.php";
    ?>
