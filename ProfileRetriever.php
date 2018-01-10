<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title></title>

        <style></style>
    </head>
    <body>
    <?php
    /**
     * Created by PhpStorm.
     * User: jorgelopezherguido
     * Date: 30/12/2017
     * Time: 12:06
     */
    // Report all PHP errors
    error_reporting(-1);
    ini_set('error_reporting', E_ALL);
    include_once 'Services/FullContact.php';

    echo "Starting"."</br>";
    // define variables and set to empty values
    $emailsListInput = "";
    ?>
    <h2>Generic Template - Input Email Addresses</h2>
    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
    <br>
        Paste the E-mails list below:<br><textarea name="emailsListInput" rows="20" cols="50"><?php echo $emailsListInput;?></textarea>
       <br>
        <input type="submit" name="submit" value="Submit">
    </form>
    <?php
    $responses = array();



    //Parse the email addresses from input into an array.
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        echo 'Submit Request Received'."</br>";

        if (empty($_POST["emailsListInput"])) {
            $emailsListInput = "";
            $emailsListInputErr = "Invalid List. It's empty.";
            echo "Invalid List. Its empty." . "</br>";
        } else {
            $emailsListInput = ($_POST["emailsListInput"]);
            $emailsArray = explode("\n", $emailsListInput);
            echo $emailsListInput."</br>";
            //cleanArray($emailsListInput);
            echo "Calling Retrieve Profiles" . "</br>";
            retrieveProfiles($emailsArray);
        }
    }

    function  retrieveProfiles($emailsArray){

        echo 'Entering Retrieve Profiles'."</br>";
        echo "No. Of Emails: ".count($emailsArray)."</br>";

        $parseJSON = false;
        $iterationNo = 1;

        //Remove duplicates from list
        $emailsArray = array_unique($emailsArray);

        foreach ($emailsArray as $itemEmail) {
            echo "Iteration No: " . $iterationNo . "</br>";
            echo "Pre-Email:[" . $itemEmail . "]"."</br>";
            $responses = '';

            echo "To CleanEmail: " . $itemEmail . "</br>";
            $itemEmail = trim(preg_replace('/\s+/', ' ', $itemEmail));
            if (!filter_var($itemEmail, FILTER_VALIDATE_EMAIL)) {
                $responses[] = array("response" => ("Invalid " . $itemEmail . " email format."));
                echo json_encode($responses) . "</br>";
                $iterationNo++;
                continue;
            }

            $emailForFullContact = $itemEmail;
            echo "Email for: " . $emailForFullContact . "</br>";
            echo 'Calling FullContact API'."</br>";
            $fullContactOutputResult = returnFullContact($emailForFullContact, $parseJSON);
            //Save the record about this email. So next time, the email is not sent to fullContact

            if (!($fullContactOutputResult)){
                echo "Full Contact couldn't find the user." . "</br>";

                //fullContact Not found
                //$clreabitOutputResult = goClearbit($emailForFullContact);

                //Upload results if true
            }

            $iterationNo++;
            //Wait 1 min
            sleep (20);

        } //foreach

    }

    ///////////////////////////////////////////////////////////////////////////

    function returnFullContact($emailForFullContact, $parseJSON)
    {
        echo "Entering FullContact Code Function" . "<br />";

        //Output
        $outputArray = array();
        //Double check folder to see if request was already performed and no need to re-ask FullContact
        $emailCleantoSearch = str_replace(".", "__", $emailForFullContact);



        //----
        if (file_exists('/Applications/MAMP/htdocs/StartUpGrind/jsonFullContact/' . $emailCleantoSearch . '.json')) {
            error_log("Reading File");
            echo "File Exists" . "<br />";
            // echo " Reading File" . "<br />";
            //File get contents
            $fileReader = file_get_contents('/Applications/MAMP/htdocs/StartUpGrind/jsonFullContact/' . $emailCleantoSearch . '.json', true);
            $resultFC = json_decode( $fileReader, true);
            //$resultFC = $fileReader;

           error_log("File Read");
        } else
        {
            //Access FullcontactAPI to retrieve the entire JSON from that person.
            echo "File doesn't exist." . "<br />";
            echo 'Call FullContact API with: '. $emailForFullContact . "</br>";

            //Access FullcontactAPI to retrieve the entire JSON from that person.
            $fullcontact = new Services_FullContact_Person('fe560a12444138d1');
            $resultFC  = $fullcontact->lookupByEmail($emailForFullContact);
            error_log("FullContact API Call made");

            if ( $resultFC->status == 200 ) {
                 echo "Results after querying API are ok" . "<br />";
                error_log("FullContact API Results are ok");

                error_log("Save Output to a File");
                //Save Output into a file
                ////////////////// SAving File ///////////////
                $jsonEncoded = json_encode($resultFC, true);
                //$newParameterEmail['emailUser'] = $emailForFullContact;
                //array_push($jsonEncoded,$newLoginHistory);
                //Add email into the json
                //echo $jsonEncoded . "<br />"

                $emailCleantoSave = str_replace(".", "__",$emailForFullContact);
                file_put_contents('/Applications/MAMP/htdocs/StartUpGrind/jsonFullContact/' . $emailCleantoSave . '.json', $jsonEncoded);
                /////////////////////////////////////////////
                //File get contents
                $fileReader = file_get_contents('/Applications/MAMP/htdocs/StartUpGrind/jsonFullContact/' . $emailCleantoSave . '.json', true);
                $resultFC = json_decode( $fileReader, true);


                error_log("File Saved.");
            }else{

                $responses[] = array("response" => ($resultFC->status));
                echo json_encode($responses);
                // echo json_encode($responses);
                echo "FullContact API Resulted on error. ->" . $emailForFullContact . "</br>";
                return false;
            }

        }

        /////-------
        echo "Checking if Parsing is needed" . "<br />";
        if ($parseJSON == true){
        //$resultFC = json_encode($resultFC, true);
        $fullContactFullName = $resultFC['contactInfo']['fullName'];

        echo "Checking Likelihood " . floatval($resultFC['likelihood']) . "</br>";
        if ((floatval($resultFC['likelihood']) > 0.75) ) {

            // echo "Full Contact Case seems ok. Parsing" . "<br />";
            echo "Full Contact Case for" . $emailForFullContact . "seems ok. Parsing" . "<br />";

            $fullContactName = $resultFC['contactInfo']['givenName'];
            // echo $fullContactName . "<br />";
            $fullContactLastName = $resultFC['contactInfo']['familyName'];
            // echo $fullContactLastName . "<br />";

            //ProfilePhotos
            if (isset($resultFC['photos'])) {
                error_log("Parsing Photos");
                $fullContactArrayPhotos = $resultFC['photos'];

                foreach ($fullContactArrayPhotos as &$socialPhoto) {

                    if ($socialPhoto['isPrimary'] == "true") {
                        $fullContactPhoto = $socialPhoto['url'];
                    }
                }//foreach
            } //isset

            if (isset($resultFC['organizations'])) {
                error_log("Parsing Organizations");
                $fullContactArrayOrganizations = $resultFC['organizations'];
                $organizationsToSort = array(count($fullContactArrayOrganizations));

                foreach ($fullContactArrayOrganizations as &$organization) {

                    if (isset($organization['isPrimary']) && $organization['isPrimary'] == 'true') {
                        echo "Name Organization: " . $organization['name'] . "</ br>";
                        $currentOrganization = $organization['name'];
                        echo "Title Job: " . $organization['title'] . "</ br>";
                        $currentTitle = $organization['title'];
                        $fullContactPosition = $currentTitle;
                        // echo $fullContactPosition . "<br />";
                        $fullContactCompany = $currentOrganization;
                        // echo $fullContactCompany . "<br />";
                        //'jobTitle' => $fullContactPosition,
                        //'companyName' => $fullContactCompany,
                        break;

                    } else {
                        if (isset($organization['current']) && $organization['current'] == 'true') {

                            if (isset($organization['startDate'])) {
                                $startDate = $organization['startDate'];
                                //echo $startDate;
                                if (strlen(trim($startDate) > 4)) {
                                    $pieces = explode("-", trim($startDate));
                                    $count = count($pieces);
                                    if ($count > 1) {
                                        $startDate = $pieces[0] . "-" . $pieces[1] . "-01";
                                    }
                                } else {
                                    $startDate = substr($startDate, 0, 4) . "-01-01";
                                }

                                $organization['startDate'] = $startDate;
                                //Add into an array with date
                                array_push($organizationsToSort, $organization);
                            }
                        }
                    }

                }

                if (count($organizationsToSort) > 0) {
                    //foreach organisation
                    echo "TwoOrganizations " . "/br/>";
                    //print_r($organizationsToSort);
                    usort($organizationsToSort, 'date_compare');
                    print_r($organizationsToSort);
                    $fullContactPosition = $organizationsToSort[1]['title'];
                    // echo $fullContactPosition . "<br />";
                    $fullContactCompany = $organizationsToSort[1]['name'];
                    // echo $fullContactCompany . "<br />";
                }

            } //isset organizations


            //Websites
            $rightsideEmail = explode("@", $emailForFullContact)[1];
            error_log("Checking with generic addresses");
            //echo "Email riht side" . $rightsideEmail;
            $listofGenericEmails = array("aol.com", "att.net", "comcast.net", "facebook.com", "gmail.com", "gmx.com", "googlemail.com", "google.com", "hotmail.com", "hotmail.co.uk", "mac.com", "me.com", "mail.com", "msn.com", "live.com", "sbcglobal.net", "verizon.net", "yahoo.com", "yahoo.co.uk", "email.com", "games.com", "gmx.net", "hush.com", "hushmail.com", "icloud.com", "inbox.com", "lavabit.com", "love.com", "outlook.com", "pobox.com", "rocketmail.com", "safe-mail.net", "wow.com", "ygm.com", "ymail.com", "zoho.com", "fastmail.fm", "yandex.com", "iname.com", "bellsouth.net", "charter.net", "comcast.net", "cox.net", "earthlink.net", "juno.com", "btinternet.com", "virginmedia.com", "blueyonder.co.uk", "freeserve.co.uk", "live.co.uk", "ntlworld.com", "o2.co.uk", "orange.net", "sky.com", "talktalk.co.uk", "tiscali.co.uk", "virgin.net", "wanadoo.co.uk", "bt.com", "sina.com", "qq.com", "naver.com", "hanmail.net", "daum.net", "nate.com", "yahoo.co.jp", "yahoo.co.kr", "yahoo.co.id", "yahoo.co.in", "yahoo.com.sg", "yahoo.com.ph", "hotmail.fr", "live.fr", "laposte.net", "yahoo.fr", "wanadoo.fr", "orange.fr", "gmx.fr", "sfr.fr", "neuf.fr", "free.fr", "gmx.de", "hotmail.de", "live.de", "online.de", "t-online.de", "web.de", "yahoo.de", "mail.ru", "rambler.ru", "yandex.ru", "ya.ru", "list.ru", "hotmail.be", "live.be", "skynet.be", "voo.be", "tvcablenet.be", "telenet.be", "hotmail.com.ar", "live.com.ar", "yahoo.com.ar", "fibertel.com.ar", "speedy.com.ar", "arnet.com.ar", "hotmail.com", "gmail.com", "yahoo.com.mx", "live.com.mx", "yahoo.com", "hotmail.es", "live.com", "hotmail.com.mx", "prodigy.net.mx", "msn.com", "yahoo.com.br", "hotmail.com.br", "outlook.com.br", "uol.com.br", "bol.com.br", "terra.com.br", "ig.com.br", "itelefonica.com.br", "r7.com", "zipmail.com.br", "globo.com", "globomail.com", "oi.com.br");


            //echo (in_array($rightsideEmail, $listofGenericEmails ) );
            if (!(in_array($rightsideEmail, $listofGenericEmails))) {
                error_log("Parsing Company Website from Email");
                $checkingemail = parse_url("http://" . $rightsideEmail)["host"];
                $fullContactCompany = ucfirst(explode(".", $rightsideEmail)[0]);
                //        echo "CompanyNme is " . $fullContactCompany . "<br />";
                $fullContactCompanyWebsite = "http://" . $rightsideEmail;

            } else {
                if (isset($resultFC['contactInfo']['websites'])) {
                    error_log("Parsing Websites");
                    $fullContactArrayWebsites = $resultFC['contactInfo']['websites'];
                    $arrayOfGenericWebsites = array("instagram", "linkedin", "facebook", "tumblr", "bit.ly", "twitter");
                    $websitesToSort = array();

                    foreach ($fullContactArrayWebsites as &$website) {
                        //Take just the intertal part of an url
                        //echo "MainDomain " . parse_url($website['url'])['host'][0] . "<br />";
                        $cleaningWebsite = parse_url($website['url'])['host'];
                        $cleaningWebsite = str_replace("http://", "", $cleaningWebsite);
                        $cleaningWebsite = str_replace("www.", "", $cleaningWebsite);
                        $cleaningWebsite = explode("/", $cleaningWebsite)[0];
                        $cleaningWebsite = explode(".", $cleaningWebsite)[0];

                        if (!(in_array($cleaningWebsite, $arrayOfGenericWebsites))) {
                            //Add into an array with date
                            array_push($websitesToSort, $website['url']);
                        }

                    }
                    $fullContactCompanyWebsite = $websitesToSort[0];
                }

                //doCompanyGoogle


            } //else of website


            //SocialProfiles - We're looking for Linkedin/Facebook and Twitter.'
            if (isset($resultFC['socialProfiles'])) {
                error_log("Parsing Social Profiles");
                $fullContactArrayProfiles = $resultFC['socialProfiles'];


                foreach ($fullContactArrayProfiles as &$Profile) {

                    switch ($Profile['type']) {
                        case "linkedin":
                            $fullContactBioLinkedIn = $Profile['bio'];
                            $fullContactLinkedInURL = $Profile['url'];
                            $fullContactLinkedInUsername = $Profile['username'];
                            break;

                        case "twitter":
                            $fullContactBioTwitter = $Profile['bio'];
                            $fullContactTwitterInURL = $Profile['url'];
                            $fullContactTwitterUsername = $Profile['username'];
                            break;

                        case "facebook":
                            $fullContactFacebookURL = $Profile['url'];
                            $fullContactFacebookUsername = $Profile['username'];
                            //$fullContactAboutMeInURL = $Profile['url'];
                            break;

                        case "aboutme":
                            $fullContactBioAboutMe = $Profile['bio'];
                            //$fullContactAboutMeInURL = $Profile['url'];
                            break;

                        case "angellist":
                            $fullContactBioAngelList = $Profile['bio'];
                            //$fullContactTwitterInURL = $Profile['url'];
                            break;

                        case "google":
                            $fullContactBioGooglePlus = $Profile['bio'];
                            break;

                        default:
                            break;

                    }


                }//foreach

                //Selecting 1)SocialNetworkLink
                if (strlen($fullContactLinkedInURL) > 0) {
                    $fullContactSocialNetwork = "Linkedin";
                    $fullContactSocialNetworkURL = $fullContactLinkedInURL;
                } else {
                    if (strlen($fullContactFacebookURL) > 0) {
                        $fullContactSocialNetwork = "Facebook";
                        $fullContactSocialNetworkURL = $fullContactFacebookURL;
                    } else {
                        $fullContactSocialNetwork = "";
                        $fullContactSocialNetworkURL = "";
                    }

                }
                //   echo $fullContactSocialNetwork . "<br />";
                //   echo $fullContactSocialNetworkURL  . "<br />";

                //Selecting 2)Bio
                if (strlen($fullContactBioAngelList) > 15) {
                    $fullContactBio = $fullContactBioAngelList;
                } else {

                    if (strlen($fullContactBioTwitter) > 15) {
                        $fullContactBio = $fullContactBioTwitter;
                    } else {
                        if (strlen($fullContactBioLinkedIn) > 15) {
                            $fullContactBio = $fullContactBioLinkedIn;
                            //$fullContactFullBio = $fullContactBioLinkedIn;
                        } else {
                            if (strlen($fullContactBioAboutMe) > 15) {
                                $fullContactBio = $fullContactBioAboutMe;
                            }
                        }
                    }
                }

                //Full Bio only from Linkedin if exists
                if (strlen($fullContactBioLinkedIn) > 5) {
                    $fullContactLongBio = substr($fullContactBioLinkedIn, 0, 150) . "...";
                }

                //  echo $fullContactBio . "<br />";
                //  echo $fullContactLongBio . "<br />";

            }//socialProfiles

            //Tags
            if (isset($resultFC['digitalFootprint']['topics'])) {
                error_log("Parsing Topics");
                $fullContactTagsTopics = $resultFC['digitalFootprint']['topics'];
                $fullContactTagCollection = array();
                $fullContactTagCollectionText = "";
                //Only two at Max.

                foreach ($fullContactTagsTopics as &$topics) {

                    if ($topics['provider'] == "aboutme") {
                        $fullContactTagCollectionText = $fullContactTagCollectionText . $topics['value'] . " ";
                        array_push($fullContactTagCollection, $topics['value']);
                    }
                }

                if (count($fullContactTagCollection) > 0) {
                    // echo $fullContactTagCollection[1]. "<br />";
                }
                if (count($fullContactTagCollection) > 1) {
                    // echo $fullContactTagCollection[2]. "<br />";
                }
            }

            //Location
            if (isset($resultFC['demographics']['locationDeduced']['deducedLocation'])) {
                error_log("Demographics");
                $fullContactLocation = $resultFC['demographics']['locationDeduced']['deducedLocation'];
                //  echo $fullContactLocation . "<br />";
            }

            $fullContactStatus = "200 OK";

            if (strlen($fullContactBio) > 5) {
                $fullContactUserStatus = substr($fullContactBio, 0, 30) . "...";
            } else {
                $fullContactUserStatus = "";
            }

            error_log("Preparing JSON");

            $results[] = array(
                'statusRequest' => $fullContactStatus,
                'name' => $fullContactName,
                'lastName' => $fullContactLastName,
                'fullName' => $fullContactFullName,
                'profilePhotoURL' => $fullContactPhoto,
                'jobTitle' => $fullContactPosition,
                'companyName' => $fullContactCompany,
                'companyWebsite' => $fullContactCompanyWebsite,
                //'companyLogoURL' => $fullContactCompanyLogoURL,
                'statusUser' => $fullContactUserStatus,
                'shortBio' => $fullContactBio,
                'longBio' => $fullContactLongBio,
                //'personalWebsite' => $row['personalWebsite'],
                'email' => $emailForFullContact,
                'twitterProfileURL' => $fullContactTwitterInURL,
                'socialNetworkType' => $fullContactSocialNetwork,
                'socialNetworkURL' => $fullContactSocialNetworkURL,
                'tagsCollection' => $fullContactTagCollectionText,
                'location' => $fullContactLocation
            );

            echo "Final Results" . "<br />";
            echo json_encode($results);
            //return $results;
            error_log("JSON Returned");
            return true;


        }
        }//API return checker likelyhood and FullName items

        //Second Option for searching a contact. --> Clearbit ??



    }//Finish of returnFullContact
    ///////////////////////////////////////////////////////////////////////
    function date_compare($a, $b)
    {
        $t1 = strtotime($a['startDate']);
        $t2 = strtotime($b['startDate']);
        return $t1 - $t2;
    }

    ?>

    </body>
</html>