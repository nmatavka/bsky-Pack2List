<?php
error_reporting(E_ERROR);
ini_set('display_errors', '1');
if (isset($_POST['submit'])) {

    $BSKY_HANDLETEST = str_replace(["@", 'bsky.app'], ["", 'bsky.social'], $_POST['handle']);
    $BSKY_PWTEST = $_POST['apppassword'];

    require "./bsky-core.php";

    // Initialize the connection
    $pdsURL = getPDS($BSKY_HANDLETEST);
    if ($pdsURL == '') {
        echo 'Invalid Username Entered. Make sure it is the full name, including the domain suffix (e.g. user.bsky.social, not just user).';
    } else {
        $bluesky = new BlueskyApi($BSKY_HANDLETEST, $BSKY_PWTEST, $pdsURL);

        if ($bluesky->hasApiKey()) {

            // Get DID for the user
            $args = [
                'actor' => $bluesky->getAccountDid()
            ];

            if ($tUsr = $bluesky->request('GET', 'app.bsky.actor.getProfile', $args)) {

                // Get all the accounts the user follows
                $tDID = $tUsr->did;
                $cursor = '';
                $arrFollowings = [];
                do {
                    $args = ['actor' => $tDID, 'limit' => 100, 'cursor' => $cursor];
                    $res = $bluesky->request('GET', 'app.bsky.graph.getFollows', $args);
                    $arrFollowings = array_merge($arrFollowings, (array)$res->follows);
                    $cursor = $res->cursor ?? '';
                } while ($cursor);

                // Get all the followers for the user
                $arrFoll = [];
                $cursor = '';
                do {
                    $args = ['actor' => $tDID, 'limit' => 100, 'cursor' => $cursor];
                    $res = $bluesky->request('GET', 'app.bsky.graph.getFollowers', $args);
                    $arrFoll = array_merge($arrFoll, (array)$res->followers);
                    $cursor = $res->cursor ?? '';
                } while ($cursor);

                // Find non-mutual follows (accounts followed by the user that do not follow back)
                $nonMutualFollows = array_diff(array_map(fn($f) => $f->did, $arrFollowings), array_map(fn($f) => $f->did, $arrFoll));

                // Unfollow non-mutual follows
                foreach ($nonMutualFollows as $did) {
                    $args = [
                        'collection' => 'app.bsky.graph.unfollow',
                        'repo' => $bluesky->getAccountDid(),
                        'record' => [
                            'subject' => $did,
                            'createdAt' => date('c'),
                            '$type' => 'app.bsky.graph.unfollow',
                        ],
                    ];
                    $res = $bluesky->request('POST', 'com.atproto.repo.createRecord', $args);
                }
            }

            $bluesky = null;
            echo "Unfollow Complete";
        } else {
            echo "Error connecting to your account. Please check the username and app password and try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Unfollow non-mutual follows</title>
</head>
<body>
    <h1>Unfollow non-mutual follows</h1>
    <?php
    require "./app-pw.php";
    ?>
    <form action="" method="POST">
        <p>Your BSky Handle: <input type="text" name="handle" placeholder="user.bsky.social" required></p>
        <p>Your BSky <a href="https://bsky.app/settings/app-passwords" target="_blank">App Password</a>: <input type="password" name="apppassword" placeholder="abcd-1234-fghi-5678" required></p>
        <input type="submit" name="submit" value="Submit">
    </form>
    <?php
    include "./footer.php";
    ?>
</body>
</html>
