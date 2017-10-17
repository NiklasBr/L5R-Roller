<?php
try {
    /*
     * Get results from the database, first normal, then with emphases. Also, the db has
     * results available for up to 'result' = 150, but that is so exceedingly rare that we
     * only go to 110.
     *
     * Using a space for key for the first set of results might seem odd, but it will be
     * removed by the trim() when processing the results. This because the default result.
     */
    $database = new PDO('mysql:host=127.0.0.1;dbname=l5r', 'root', '');
    $results[' ']    = $database->query('select * from `explode_no_emphasis` where `result` < 111 order by `roll`;');
    $results['e']    = $database->query('select * from `explode_and_emphasis` where `result` < 111 order by `roll`;');
    $results['ne']   = $database->query('select * from `no_explode_no_emphasis` where `result` < 111 order by `roll`;');
    $results['e_ne'] = $database->query('select * from `no_explode_and_emphasis` where `result` < 111 order by `roll`;');
    $results['_e9']  = $database->query('select * from `explode_9_10_no_emphasis` where `result` < 111 order by `roll`;');
    $results['e_e9'] = $database->query('select * from `explode_9_10_and_emphasis` where `result` < 111 order by `roll`;');
    $database = null;
} catch (PDOException $e) {
    /* 
     * Fail gracefully and go on 
    var_dump($e);
    */
}

// Process the database queries
// Calculates sums by taking each DB query result and adding to the $sums array
$processed_results = [];
foreach ($results as $key=>$part) {
    foreach ($part as $result) {
        $roll_type = trim($result['roll'] . 'k' . $result['keep'] . $key);
        $processed_results[$roll_type][$result['result']] = (int) $result['count'];
        $sums[$roll_type] = array_sum($processed_results[$roll_type]);
    }
}

/*
 * Combines all major calculations for speed.
 *
 * There are simply too many iterations over the thousands results and
 * millions of rolls otherwise. If I later find that this still leads to
 * unwanted slowdowns I might consider generating a static page. While letting a
 * cron job update it now and then.
 *
 */
foreach ($processed_results as $roll_type=>$results) {
    $diff_sum = 0;
    $total = 0;
    $js_array[$roll_type] = '[';

    /* 
     * Creates an array with our data to use with on-page JavaScript.
     *
     * If less than ten rolls in the row have a result there are so few that effectively a
     * zero will work. We therefore return a 0 because JS might choke on extremely small
     * floats otherwise. Saves the percentage value in a list.
     *
     */
    foreach ($results as $result=>$rolls) {
        $total = $total + ($result * $rolls);

        if ($rolls > 10) {
            $js_array[$roll_type] .= '[' . $result . ',' . $rolls/$sums[$roll_type]*100 . '],';
        } else {
            $js_array[$roll_type] .= '[' . $result . ',0],';
        }

    }
    $js_array[$roll_type] .= ']';

    // Calculate averages. Saves the average result (rounded to one decimal) of the roll
    $averages[$roll_type] = round($total/$sums[$roll_type], 1);

    /*
     * Saves the standard deviations or each result (rounded to one decimal) of the roll
     * a number of times equal to $rolls, do this with $result
     *
     * Then calculate mean of $diff_array
     * Then calculate the difference from the mean
     */
    foreach ($results as $result=>$rolls) {
        $diff_sum = $diff_sum + ($rolls * (pow($result - $averages[$roll_type], 2)));
    }

    /*
     * Save the square root of the average of the diffs, rounded to one decimal
     *
     */
    $standard_deviations[$roll_type] = round(sqrt($diff_sum / $sums[$roll_type]), 1);
}


/*
 * Prints the contents of a JS-compatible dictionary. Takes a regular PHP array
 *
 */
function print_js_dict($array) {
    foreach ($array as $key=>$part) {
        echo "                '" , $key , "' : " , $part , ",\n";
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <!--Curious, eh? Nice!
        Want to fork and build your own version? Have a look at https://github.com/NiklasBr/L5R-Roller
        Come back soon. You are the best!
        // Niklas Brunberg
    -->
    <title>L5R Roll and Keep Probabilities</title>
    <link rel="stylesheet" type="text/css" href="/style.css">
    <script type="text/javascript" src="/jquery.js"></script>
    <script type="text/javascript" src="flotr/flotr2.min.js"></script>
    <script type="text/javascript">
        $(document).ready( function() {
            // If JS is active, hide any warnings
            $(".no-js").hide();

            // Used to return the part of the array from which data is to be selected
            function get_context() {
                if ($("#emphasis").is(":checked")) {
                    if ($("#explode").is(":checked")) {
                        if ($("#explode_9_and_10").is(":checked")) {
                            return $('#navigation input.active').attr("data-roll") + "e_e9";
                        } else {
                            return $('#navigation input.active').attr("data-roll") + "e";
                        }
                    } else {
                        return $('#navigation input.active').attr("data-roll") + "e_ne";
                    }
                } else {
                    if ($("#explode").is(":checked")) {
                        if ($("#explode_9_and_10").is(":checked")) {
                            return $('#navigation input.active').attr("data-roll") + "_e9";
                        } else {
                            return $('#navigation input.active').attr("data-roll");
                        }
                    } else {
                        return $('#navigation input.active').attr("data-roll") + "ne";
                    }
                }
            }
            
            // Get and return the type of plot that will be displayed in the graph
            function get_plot() {
                if ($("#cumulative").is(":checked")) {
                    return $.data.plots_cumulative[get_context()];
                } else {
                    return $.data.plots[get_context()];
                }
            }

            // Updates the interface with the selected data
            function calc_perc_and_avg(break_at) {
                var current_data = get_plot();
                var divided_data = current_data.slice((break_at-1), current_data.length);

                var sum = 0;
                for (var i = 0; i < divided_data.length; i++) {
                    sum = sum + divided_data[i][1];
                }

                // Updates visible numbers
                if ($("#cumulative").is(":checked")) {
                    $("#percent").text(Math.round(current_data[break_at-1][1]));
                } else {
                    $("#percent").text(Math.round(sum));
                }
                $("#average").text($.data.averages[get_context()]);
                $("#std_deviation").text($.data.std_deviations[get_context()]);
            }

            // Looks for clicks on the navigation buttons
            $(document).on('click', '#navigation input', function(event) {
                $(this).addClass('active');
                $('#navigation input').not(this).removeClass('active');

                graph_options.title = "Rolling " + $(this).attr("data-roll").replace(/k/, " keeping ") + ' dice';
                calc_perc_and_avg($("#target").val());

                Flotr.draw(document.getElementById("chart"), { data : get_plot() }, graph_options);
                // Update link for permalinks to certain rolls
                document.location.hash = $('#navigation input.active').attr("data-roll");
            });

            // Looks for changes in the TN input box
            $(document).on('input', '#target', function() {
                calc_perc_and_avg($(this).val());
//                results_plot.plugins.canvasOverlay.get("target_number").options.x = $("#target").val();
//                results_plot.replot();
                Flotr.draw(document.getElementById("chart"), { data : get_plot() }, graph_options);
            });

            // Looks for changes in the emphasis and explode check boxes
            $(document).on('change', '#emphasis, #explode, #explode_9_and_10, #cumulative', function() {
                calc_perc_and_avg($("#target").val());
                Flotr.draw(document.getElementById("chart"), { data : get_plot() }, graph_options);
            });

            var graph_options = {
                title : "Graph",
                fontColor : "#5e5e5e",
                lines : {
                    show : true,
                    color : '#123564',
                    fillColor : "#123564",
                    fill : true
                },
                xaxis : {
                    ticks : [1,5,10,15,20,25,30,35,40,45,50,55,60,65,70,75,80,85,90,95,100,105,110],
                    title : "Result",
                    min : 1,
                    max : 110
                },
                yaxis : {
                    ticks : [0,5,10,15,20],
                    title : "%",
                    min : 0,
                    max : 23,
//                    scaling : 'logarithmic',
//                    transform :  function(v) {return Math.log(v+0.1); /*move away from zero*/}, 
                    tickDecimals: 3,
                },
                grid : {
                    color : '#5e5e5e',
                    backgroundColor : '#fff'
                }
            };

            // Averages
            $.data.averages = {
                <?php print_js_dict($averages); ?>
            };

            // Standard deviations
            $.data.std_deviations = {
                <?php print_js_dict($standard_deviations); ?>
            };

            // Coordinates
            $.data.plots = {
                <?php print_js_dict($js_array); ?>
            };
            
            // Create cumulative coordinates. These might move to the PHP part later depending on 
            // bandwidth/processing considerations
            $.data.plots_cumulative = new Object;
            $.each($.data.plots, function(rollType, theResults) {
                var newList = [];
                var savedRoll = 0;
                for (var i = 0; i < theResults.length; i++) {
                    savedRoll = savedRoll + theResults[i][1];
                    newList.push([theResults[i][0], 100-savedRoll]);
                }
                $.data.plots_cumulative[rollType] = newList;
            });

            // Builds the navigation buttons
            $.each($.data.plots, function(value) {

                var new_button = $('<input type="button">');
                $(new_button)
                    .val(value.replace("r", ""))
                    .attr("data-roll", value)
                    .attr("class", value);

                // Match each into a row for a nice navigation
                if (value.match(/k1$/)) {
                    $("#xk1").append(new_button);
                }

                else if (value.match(/k2$/)) {
                    $("#xk2").append(new_button);
                }

                else if (value.match(/k3$/)) {
                    $("#xk3").append(new_button);
                }

                else if (value.match(/k4$/)) {
                    $("#xk4").append(new_button);
                }

                else if (value.match(/k5$/)) {
                    $("#xk5").append(new_button);
                }

                else if (value.match(/k6$/)) {
                    $("#xk6").append(new_button);
                }

                else if (value.match(/k7$/)) {
                    $("#xk7").append(new_button);
                }

                else if (value.match(/k8$/)) {
                    $("#xk8").append(new_button);
                }

                else if (value.match(/k9$/)) {
                    $("#xk9").append(new_button);
                }

                else if (value.match(/k10$/)) {
                    $("#xk10").append(new_button);
                }

            });

            // Check the location hash to see if any roll is saved and activate that roll if true, else activate the first roll/button
            if (document.location.hash.length > 0) {
                var reg = new RegExp("#");
                if ($(document.location.hash.replace(reg, "."))) {
                    $(document.location.hash.replace(reg, ".")).addClass("active");
                }
            } else {
                $("#navigation input:first").addClass("active");
            }
            calc_perc_and_avg($("#target").val());
            graph_options.title = "Rolling " + $('#navigation input.active').attr("data-roll").replace(/k/, " keeping ") + ' dice';
            Flotr.draw(document.getElementById("chart"), { data : get_plot() }, graph_options);
            console.log(get_plot());
        });
    </script>
</head>
<body>
    <div id="notice"><p class="no-js">This page contains interactive elements, to use it you need to activate JavaScript.</p></div>
    <div id="chart"></div>
    <div id="result">
        Probability to succeed is <span id="percent"></span>% and the average result is <span id="average"></span> and <span class="help" title="Standard Deviation">σ <span id="std_deviation"></span></span>
    </div>
    <form>
        <span class="tn_wrap"><label for="target" title="TN to hit">Target Number</label><input type="number" min="1" max="110" id="target" value="15"></span>
        <span class="options">
            <span class="check_wrap"><label for="emphasis">With emphasis</label><input type="checkbox" id="emphasis"></span>
            <span class="extra_options">
                <span class="check_wrap"><label for="explode">Explode</label><input type="checkbox" id="explode" checked></span>
                <span class="check_wrap"><label for="explode_9_and_10">Explode on 9 and 10</label><input type="checkbox" id="explode_9_and_10"></span>
                <span class="check_wrap"><label for="cumulative">Cumulative graph</label><input type="checkbox" id="cumulative"></span>
            </span>
        </span>
    </form>
    <div id="navigation">
        <div id="xk1" class="group"></div>
        <div id="xk2" class="group"></div>
        <div id="xk3" class="group"></div>
        <div id="xk4" class="group"></div>
        <div id="xk5" class="group"></div>
        <div id="xk6" class="group"></div>
        <div id="xk7" class="group"></div>
        <div id="xk8" class="group"></div>
        <div id="xk9" class="group"></div>
        <div id="xk10" class="group"></div>
    </div>
    <footer>
        <span class="deco"></span>
        <p><strong>What is this?</strong> This is a visualization of the Roll and Keep system used in <a href="http://www.l5r.com/rpg/">Legend of the Five Rings</a> role playing game. If you like this page you can support me by buying the books through these Amazon affiliate links for <a href="http://www.amazon.com/Legend-Five-Rings-4th-Edition/dp/1594720525/?_encoding=UTF8&amp;s=books&amp;tag=markthespot-20">Legend of the Five Rings</a>, <a href="http://www.amazon.com/The-Great-Clans-Legend-Edition/dp/1594720622/?_encoding=UTF8&amp;s=books&amp;tag=markthespot-20">The Great Clans</a>, <a href="http://www.amazon.com/Emerald-Empire-Edition-Legend-Rings/dp/1594720568/?_encoding=UTF8&amp;s=books&amp;tag=markthespot-20">Emerald Empire</a>, <a href="http://www.amazon.com/Imperial-Histories-L5r-Aeg/dp/1594720630/?_encoding=UTF8&amp;s=books&amp;tag=markthespot-20">Imperial Histories</a>, <a href="http://www.amazon.com/Enemies-Empire-AEG-Team/dp/159472055X/?_encoding=UTF8&amp;s=books&amp;tag=markthespot-20">Enemies of the Empire</a> and I will get a small kick-back.</p>
        <span class="deco"></span>
        <p class="info">This page is not affiliated with Alderac Entertainment Group.</p>
    </footer>
</body>
</html>
