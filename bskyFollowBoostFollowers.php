<?php
error_reporting(E_ERROR);
ini_set('display_errors', '1');
if (isset($_POST['submit'])) {

    $BSKY_HANDLETEST = str_replace(["@", 'bsky.app'], ["", 'bsky.social'], $_POST['handle']);
    $BSKY_PWTEST = $_POST['apppassword'];
    $targetUser = str_replace("@", "", $_POST['targetUser']);
    $numAccts = $_POST['numAccts'];
    $copyCap = .1; // Limit to only copying 10% of the followers from the account; adjust as desired

    require "./bsky-core.php";

    // Initialize the connection
    $pdsURL = getPDS($BSKY_HANDLETEST);
    if ($pdsURL == '') {
        echo 'Invalid Username Entered. Make sure it is the full name, including the domain suffix (e.g. user.bsky.social, not just user).';
    } else {
        $bluesky = new BlueskyApi($BSKY_HANDLETEST, $BSKY_PWTEST, $pdsURL);

        if ($bluesky->hasApiKey()) {

            // Get DID for the user from which to copy
            $args = [
                'actor' => $targetUser
            ];

            if ($tUsr = $bluesky->request('GET', 'app.bsky.actor.getProfile', $args)) {

                // Get all the followers for that user
                $tDID = $tUsr->did;
                $tFCount = $tUsr->followersCount;
                $cursor = '';
                $arrFoll = [];
                do {
                    $args = ['actor' => $tDID, 'limit' => 100, 'cursor' => $cursor];
                    $res = $bluesky->request('GET', 'app.bsky.graph.getFollowers', $args);
                    $arrFoll = array_merge($arrFoll, (array)$res->followers);
                    $cursor = $res->cursor ?? '';
                } while ($cursor);

                // Now loop to NN accounts (or % cap) and add those as follows
                $numTries = min(round(count($arrFoll) / $copyCap), $numAccts);
                $randAccts = array_rand($arrFoll, $numTries);
                foreach ($randAccts as $acct) {
                    // Add as a follow
                    $args = [
                        'collection' => 'app.bsky.graph.follow',
                        'repo' => $bluesky->getAccountDid(),
                        'record' => [
                            'subject' => $arrFoll[$acct]->did,
                            'createdAt' => date('c'),
                            '$type' => 'app.bsky.graph.follow',
                        ],
                    ];
                    $res = $bluesky->request('POST', 'com.atproto.repo.createRecord', $args);
                }
            }

            $bluesky = null;
            echo "Import Complete";
        } else {
            echo "Error connecting to your account. Please check the username and app password and try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Follow a random chunk of users from someone else's followers</title>
</head>
<body>
    <h1>Follow a random chunk of users from someone else's followers</h1>
    <?php
    require "./app-pw.php";
    ?>
    <form action="" method="POST">
        <p>Your BSky Handle: <input type="text" name="handle" placeholder="user.bsky.social" required></p>
        <p>Your BSky <a href="https://bsky.app/settings/app-passwords" target="_blank">App Password</a>: <input type="password" name="apppassword" placeholder="abcd-1234-fghi-5678" required></p>
        <p>User whose followers to follow: <input type="text" name="targetUser" placeholder="username.domain.name" required></p>
        <p>How many random users to follow?: <input type="text" name="numAccts" placeholder="25" required> *Up to 10% of their total followers or this number, whichever is larger, will be followed.</p>
        <input type="submit" name="submit" value="Submit">
    </form>
    <?php
    include "./footer.php";
    ?>
</body>
</html>
