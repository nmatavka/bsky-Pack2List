<?php
error_reporting(E_ERROR);
ini_set('display_errors', '1');
if(isset($_POST['submit'])) {


    $BSKY_HANDLETEST=str_replace(["@",'bsky.app'],["",'bsky.social'],$_POST['handle']);
    $BSKY_PWTEST=$_POST['apppassword'];
    $listURL=$_POST['sourceurl'];



    function bsky_List2ModList($bsky, $listAT) {

        //read the old list for accounts to add to the list
        $cursor='';
        $spList=[];
        do {
            $args=['list'=>$listAT,'limit'=>100,'cursor'=>$cursor];
            $res=$bsky->request('GET','app.bsky.graph.getList',$args);
            $spList=array_merge($spList,(array)$res->items);
            $cursor=$res->cursor;
            $packListName=$res->list->name;
        }
        while ($cursor);
        
        $args=[  'collection' => 'app.bsky.graph.list',
        'repo' => $bsky->getAccountDid(),
        'record' => [
            'createdAt' => date('c'),
            '$type' => 'app.bsky.graph.list',
            'purpose' => 'app.bsky.graph.defs#modlist',
            'name'=>$packListName .'-Mod' ,
            'description'=> "ModList imported from the User List at ". $_POST['sourceurl'] . ", powered by https://nws-bot.us/bskyList2ModList.php and @wandrme.paxex.aero.",
            ],
        ];

        if($data2 = $bsky->request('POST', 'com.atproto.repo.createRecord', $args)){
            $newListURI=$data2->uri;

   

            if($spList){
                foreach($spList as $listItem){
                    //Add the user to the pack list
                    $args=[  'collection' => 'app.bsky.graph.listitem',
                    'repo' => $bsky->getAccountDid(),
                    'record' => [
                        'createdAt' => date('c'),
                        '$type' => 'app.bsky.graph.listitem',
                        'subject'=> $listItem->subject->did,
                        'list'=> $newListURI,
                        ],
                    ];
                    $bsky->request('POST', 'com.atproto.repo.createRecord', $args);
                }
                return ($newListURI);
            }
            else {
            echo "Couldn't find that source list. Please check the URL and try again.";
            }
        }
    }


    function bskySPs($bsky, $userHandle,$packID) {
        //get the pack at: URI from the base URL
    
        $args = [
            'actor' => $userHandle
        ];

            if($data = $bsky->request('GET', 'app.bsky.graph.getActorStarterPacks', $args)){
                #iterate the JSON, looking for the pack with the ID that was passed in
                
                foreach ($data->starterPacks as $item){
                    $packURI=$item->uri;
                    $arrPackCheck=explode('/',$packURI);
                    $packCode=$arrPackCheck[count($arrPackCheck)-1];
                    if ($packCode==$packID){
                        //get the list at from the pack
                        $args = [
                            'starterPack' => $packURI
                        ];
                        if($data = $bsky->request('GET', 'app.bsky.graph.getStarterPack', $args)){
                            //get the parameters from the pack
                            $packListUri=$data->starterPack->list->uri;
                            return $packListUri;
                        }
                    }
                }
            } else {
                return false;
            }
            return false;
    }

    function bskyListATs($bsky, $userHandle,$listID) {
        //get the pack at: URI from the base URL
    
        $args = [
            'actor' => $userHandle, 'limit'=>100
        ];

            if($data = $bsky->request('GET', 'app.bsky.graph.getLists', $args)){
                #iterate the JSON, looking for the pack with the ID that was passed in
                
                foreach ($data->lists as $item){
                    $listURI=$item->uri;
                    $arrlistCheck=explode('/',$listURI);
                    $listCode=$arrlistCheck[count($arrlistCheck)-1];
                    if ($listCode==$listID){
                        return  $listURI;
                    }
                }
            } else {
                return '';
            }
    }


    ?>

    <?php
    require "./bsky-core.php";
    ?>
    
    <?php
    //Run this crap
    //init the connection
    $pdsURL=getPDS($BSKY_HANDLETEST);
    if ($pdsURL==''){
        echo 'Invalid Username Entered. Make sure it is the full name, including the domain suffix (e.g. user.bsky.social, not just user).';
    }
    else {
        $bluesky = new BlueskyApi($BSKY_HANDLETEST, $BSKY_PWTEST,$pdsURL);


        $listURL=deParam($listURL);
        
        if($bluesky->hasApiKey()){



            $arrList=explode('/',$listURL);
            $listUserHandle=$arrList[count($arrList)-3];
            $listID=$arrList[count($arrList)-1];



            $listAT=bskyListATs($bluesky,$listUserHandle,$listID);
            if ($listAT!=''){
                //Came back with an at: URI, so I can now fetch the Starter Pack and parse for the list details inside
                if ($newList=bsky_List2ModList($bluesky,$listAT)){
                    $arrList=explode('/',$newList);
                    $listID=$arrList[count($arrList)-1];
                    echo('<p><a target="_blank" href="https://bsky.app/profile/'. $BSKY_HANDLETEST .'/lists/' .$listID.'">New Mod List Created</a></p>');
                }
            }
            else{
                echo "Could not find that Source Lise. Please check the URL and try again.";
            }

            $bluesky=null;
            echo '<p>Import Complete</p><p>Check your <a href="https://bsky.app/moderation/modlists" target="_blank">Moderation Lists</a> for more details.';
        }
        else{
            echo "Error connecting to your account. Please check the username and app password and try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Migrate Content List to Mod List</title>
</head>
<body>
    <h1>Migrate Content List to Mod List</h1>
    <?php
require "./app-pw.php";
?>
    <form action="" method="POST">
        <p>Your BSky Handle: <input type="text" name="handle" placeholder="user.bsky.social" required></p>
        <p>Your BSky <a href="https://bsky.app/settings/app-passwords" target="_blank">App Password</a>: <input type="password" name="apppassword" placeholder="abcd-1234-fghi-5678" required></p>
        <p>Source List URL: <input type="text" name="sourceurl" placeholder="https://bsky.app/profile/wandrme.paxex.aero/lists/3jzxvt5ms372z" required></p>
        <input type="submit" name="submit" value="Submit">
    </form>
    <?php
include "./footer.php";
?>
