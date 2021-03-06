<?php
require_once('config.php');
require('cronofy.php');
require('process.php');

date_default_timezone_set('America/Indianapolis');

$userTimeZone = date_default_timezone_get();
$cronofy = new Cronofy(array(
    "access_token" => $icloudToken
));

$fromDate = new DateTime(date("Y-m-d"));
$toDate = clone $fromDate;
$fromDateString = $fromDate->modify('+-7 days')->format("Y-m-d");
$toDateString = $toDate->modify('+6 days')->format("Y-m-d");

$events = $cronofy->read_events(
    array(
        'tzid' => $userTimeZone,
        'calendar_ids' => $targetCalendar,
        'from' => $fromDateString,
        'to' => $toDateString
    )
);

$eventDetails = $events->first_page['events'];
$jsonEvents = [];
foreach ($eventDetails as $event) {
    $startTime = new DateTime($event['start']); // $event['start'] === '2019-03-30T13:00:00Z' i.e. GMT
    $startTime->setTimezone(new DateTimeZone($userTimeZone)); //'2019-03-30 09:00:00.000000'
    $startTimeString = $startTime->format(DateTime::ISO8601);

    $endTime = new DateTime($event['end']);
    $endTime->setTimezone(new DateTimeZone($userTimeZone));
    $endTimeString = $endTime->format(DateTime::ISO8601);

    $newJsonEvent = [];
    $newJsonEvent['title'] = $event['summary'];
    $newJsonEvent['description'] = $event['description'];

    $startHour = substr($startTimeString, strpos($startTimeString, "T") + 1, 2);
    if ($startHour == '00') { // i.e. Midnight, then it's an ALl Day event
        $newJsonEvent['start'] = $startTimeString;
        $newJsonEvent['allDay'] = true;
    } else {
        $newJsonEvent['start'] = $startTimeString;
        $newJsonEvent['end'] = $endTimeString;
    }
    array_push($jsonEvents, $newJsonEvent);
}
$jsonEvents = json_encode($jsonEvents);

/*-------------------------------------------------------
Load Tasks from Snap Radar via Sheet Best
-------------------------------------------------------*/
$taskSourceUrl = 'https://sheet.best/api/sheets/2f616a65-ea9e-420e-9592-8a7112d002fd';
//$tasksToSchedule = file_get_contents($taskSourceUrl);
$tasksToSchedule = '[{"id":"72258","title":"** asdfCo-Founder","status":"Church letter","category":"Platinum","updated":"02/06/2020 10:50","5":"","6":"** Co-Founder: Church letter"},{"id":"62149","title":"** Marketing Website","status":"Pick a theme and run with it!","category":"Platinum","updated":"01/24/2020 12:44","5":"","6":"** Marketing Website: Pick a theme and run with it!"},{"id":"29348","title":"MC 1065 -> K1s","status":"P 2/5: Finishing up","category":"MC EOY","updated":"02/06/2020 10:51","5":"","6":"MC 1065 -> K1s: P 2/5: Finishing up"},{"id":"35628","title":"** Spark Theme","status":"Map to Platinum parts","category":"Platinum","updated":"02/05/2020 08:24","5":"","6":"** Spark Theme: Map to Platinum parts"},{"id":"99332","title":"Groc Mob","status":"Build failed. Fix it, then sideload/TestFlight","category":"Groc Mob","updated":"01/24/2020 07:54","5":"","6":"Groc Mob: Build failed. Fix it, then sideload/TestFlight"},{"id":"16701","title":"Snap Radar","status":"Switch to Bmarcelino Stacked Cards","category":"Snap Radar","updated":"02/06/2020 10:50","5":"","6":"Snap Radar: Switch to Bmarcelino Stacked Cards"},{"id":"39664","title":"Leaking roof","status":"Pending Paul White visit","category":"Home Mech","updated":"02/06/2020 17:22","5":"","6":"Leaking roof: Pending Paul White visit"},{"id":"14258","title":"Nate Webster","status":"Call 2/6 7PM","category":"Platinum","updated":"02/05/2020 10:16","5":"","6":"Nate Webster: Call 2/6 7PM"},{"id":"25225","title":"New shower","status":"Call Tim Wegener a plumber","category":"Home","updated":"01/24/2020 07:56","5":"","6":"New shower: Call Tim Wegener a plumber"},{"id":"87704","title":"Pat Rubeck","status":"Pending payment (still)","category":"Boost","updated":"02/06/2020 17:22","5":"","6":"Pat Rubeck: Pending payment (still)"},{"id":"","title":"HH Pre-fund payroll","status":"P 2/7 ACH","category":"Boost","updated":"02/06/2020 17:22","5":"","6":"HH Pre-fund payroll: P 2/7 ACH"}]';
$tasksArray = json_decode($tasksToSchedule);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cronofy</title>
    <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.0/jquery-ui.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>

    <script src='https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.9.0/moment.min.js'></script>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.9.0/fullcalendar.min.js'></script>

    <link href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css" rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet"><!-- Bootstrap Core CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" type="text/css"><!-- Font Awesome CSS -->

    <link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.9.0/fullcalendar.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.9.0/fullcalendar.print.min.css" media="print" rel="stylesheet">
    <style>
        body {
            margin-top: 40px;
            text-align: center;
            font-size: 14px;
            font-family: "Lucida Grande", Helvetica, Arial, Verdana, sans-serif;
        }

        #wrap {
            width: 1100px;
            margin: 0 auto;
        }

        #external-events {
            float: left;
            width: 150px;
            padding: 0 10px;
            margin: 50px 0 0 50px;
            border: 1px solid #ccc;
            background: #eee;
            text-align: left;
        }

        #external-events h4 {
            font-size: 16px;
            margin-top: 0;
            padding-top: 1em;
        }

        #external-events .fc-event {
            margin: 10px 0;
            padding: 10px;
            cursor: move;
        }

        #external-events p {
            margin: 1.5em 0;
            font-size: 11px;
            color: #666;
        }

        #external-events p input {
            margin: 0;
            vertical-align: middle;
        }
    </style>
</head>

<body>
    <div id="page-wrapper">
        <div class="row" id="open-top row">
            <div class="col-lg-12">
                <!--<h1 class="page-header text-center">Cronofy Test Drive</h1>-->
            </div id="close-col-lg-12">
        </div id="close-top row">
        <div class="row text-center">
            <!--<div class="col-md-1 text-center"></div>-->
            <div class="col-lg-2 col-md-2">
                <div id="external-events">
                    <h4>Tasks to Schedule</h4>
                    <?php
                    $i = 1;
                    foreach ($tasksArray as $task) {
                        echo "<div class=\"fc-event\" id=\"Event {$i}\">" . $task->title . "</div>\n";
                        ++$i;
                    }
                    ?>
                </div>
            </div id="close-left-sidebar">
            <!--<div class="col-md-1">LEFT BUFFER</div>-->
            <!-- /.col-md-1 -->
            <div class="col-md-8">
                <div class="full-calendar" id="calendarDiv"></div>
            </div><!-- /.MAIN CONTENT -->
            <div class="col-lg-2 col-md-2">RIGHT SIDEBAR</div>
        </div><!-- /.row -->
    </div><!-- /#page-wrapper -->
    <script>
        $(document).ready(function() {
            console.info("Starting $document.ready");
            $("#bottom").text("");
            /* initialize the external events
            -----------------------------------------------------------------*/
            $('.fc-event').each(function() {
                // store data so the calendar knows to render an event upon drop
                $(this).data('event', {
                    title: $.trim($(this).text()), // use the element's text as the event title
                    stick: true // maintain when user navigates (see docs on the renderEvent method)
                });

                // make the event draggable using jQuery UI
                $(this).draggable({
                    zIndex: 999,
                    revert: true, // will cause the event to go back to its
                    revertDuration: 0 //  original position after the drag
                });
            });

            var todaysDate = moment().startOf('day');
            var yearMonth = todaysDate.format('YYYY-MM');
            var YESTERDAY = todaysDate.clone().subtract(1, 'day').format('YYYY-MM-DD');
            var TODAY = todaysDate.format('YYYY-MM-DD');
            var TOMORROW = todaysDate.clone().add(1, 'day').format('YYYY-MM-DD');
            var DayAfterTmrw = todaysDate.clone().add(2, 'day').format('YYYY-MM-DD');
            var DaysHence3 = todaysDate.clone().add(3, 'day').format('YYYY-MM-DD');

            $('#calendarDiv').fullCalendar({
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'agendaDay,agendaWeek,month'
                },
                theme: true,
                themeButtonIcons: {
                    prev: 'circle-triangle-w',
                    next: 'circle-triangle-e',
                    prevYear: 'seek-prev',
                    nextYear: 'seek-next'
                },
                defaultDate: todaysDate,
                defaultView: 'agendaWeek',
                minTime: '08:00:00',
                maxTime: '22:00:00',
                contentHeight: 'auto', //fixed columns overhanging bottom border
                droppable: true,
                editable: true,
                drop: function() {
                    //$(this).remove();
                },
                eventBackgroundColor: '#337ab7', //Bootstrap Dark Blue "Primary"
                //eventBackgroundColor: '#5cb85c', //Bootstrap Green "Success"
                eventColor: '#555',
                eventRender: function(event, element) {
                    $(element).popover({
                        title: event.title,
                        content: event.description,
                        trigger: 'hover',
                        placement: 'auto under',
                        delay: {
                            "hide": 300
                        }
                    });
                },
                eventDrop: function(info) {
                    //alert(info.event.title + " was moved.");
                    console.info(info.event.title + "was dropped.");
                },
                events: <?php echo $jsonEvents; ?>
            }); //close fullCalendar
        }); //close $(document).ready
    </script>
</body>

</html>