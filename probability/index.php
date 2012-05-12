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
	$results[' '] = $database->query('select * from `explode_no_emphasis` where `result` < 111 order by `roll`');
	$results['e'] = $database->query('select * from `explode_and_emphasis` where `result` < 111 order by `roll`');
	$results['ne'] = $database->query('select * from `no_explode_no_emphasis` where `result` < 111 order by `roll`');
	$results['e_ne'] = $database->query('select * from `no_explode_and_emphasis` where `result` < 111 order by `roll`');
	$results['_e9'] = $database->query('select * from `explode_9_10_no_emphasis` where `result` < 111 order by `roll`');
	$results['e_e9'] = $database->query('select * from `explode_9_10_and_emphasis` where `result` < 111 order by `roll`');
	$database = null;
} catch (PDOException $e) {
    /* 
     * Fail gracefully and go on 
    var_dump($e);
    */
}

// Process the database queries
foreach ($results as $key=>$part) {
    foreach ($part as $result) {
        $type = trim($result['roll'] . 'k' . $result['keep'] . $key);
        $processed_results[$type][$result['result']] = $result['count']*1;
        $sums[$type] = calculate_sums($processed_results[$type]);
    }
}


// Calculates sums by taking each DB query result and adding to the $sums array
function calculate_sums($numbers) {
    $sum = 0;
    foreach ($numbers as $number) {
        $sum = $number + $sum;
    }
    return $sum;
}

/* 
 * Creates an array with our data to use with on page Javascript.
 *
 * If less than ten rolls in the row have a result there are so few that effectively a 
 * zero will work. We therefore return a 0 because JS might choke on extremely small 
 * floats otherwise. Returns the percentage value in a list.
 */
function build_array($result, $sum) {
    $return = '[';
    foreach ($result as $result=>$rolls) {
        if ($rolls > 10) {
            $return .= '[' . $result . ',' . $rolls/$sum*100 . '],';
        } else {
            $return .= '[' . $result . ',0],';
        }
    }
    $return .= ']';
    return $return;
}

// Returns the average result (rounded to one decimal) of the roll
function calculate_average($results, $sum) {
    $total = 0;
    foreach ($results as $result=>$rolls) {
        $total =  $total + ($result*$rolls);
    }
    return round($total/$sum, 1);
}

// Returns the standard deviations or each result (rounded to one decimal) of the roll
function calculate_std_deviation($results, $sum) {
    $diff_sum = 0;
    $total = 0;
    
    // First calculate mean
    foreach ($results as $result=>$rolls) {
         $total =  $total + ($result*$rolls);
     }
     $mean = $total/$sum;
     
     // Then calculate the difference from the mean
     foreach ($results as $result=>$rolls) {
         // a number of times equal to $rolls, do this with $result
         for ($i = 0; $i < $rolls; $i++) {
             // Then calculate mean of $diff_array
             $diff_sum = $diff_sum+pow($result-$mean, 2);
         }
     }

    // Return the square root of the average of 
    return round(sqrt($diff_sum/$sum), 2);
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <!--
        Curious, eh? Nice! 

        Want to fork and build your own version? Have a look at https://github.com/NiklasBr/L5R-Roller        
        
        Come back soon. You are the best!
        
         // Niklas Brunberg, fyrkantigt.se/en/projekt/wikipics
    -->
    <title>L5R Roll and Keep Probabilities</title>
    <link rel="stylesheet" type="text/css" href="/style.css">
    <link rel="stylesheet" type="text/css" href="jquery.jqplot.min.css">
    <script type="text/javascript" src="/jquery.js"></script>
    <script type="text/javascript" src="jquery.jqplot.min.js"></script>
    <script type="text/javascript" src="plugins/jqplot.logAxisRenderer.min.js"></script>
    <script type="text/javascript" 
    src="plugins/jqplot.canvasTextRenderer.min.js"></script>
    <script type="text/javascript" src="plugins/jqplot.canvasAxisLabelRenderer.min.js"></script>
    <script type="text/javascript" src="plugins/jqplot.canvasOverlay.min.js"></script>
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

            // Updates the interface with the selected data
            function calc_perc_and_avg(break_at) {
                var current_data = $.data.plots[get_context()];
                var divided_data = current_data.slice((break_at-1), current_data.length);
            
                var sum = 0;
                for (var count = 0; count < divided_data.length; count++) {
                    sum = sum + divided_data[count][1];
                }
                
                $("#percent").text(Math.round(sum));
                $("#average").text($.data.averages[get_context()]);
            }
            
            // Looks for clicks on the navigation buttons
            $(document).on('click', '#navigation input', function(event) {
                $(this).addClass('active');
                $('#navigation input').not(this).removeClass('active');
                
                results_plot.title.text = "Rolling " + $(this).attr("data-roll") + ', exploding dice';
                calc_perc_and_avg($("#target").val());
                results_plot.series[0].data = $.data.plots[get_context()];
                results_plot.replot();
                
                // Update link for permalinks to certain rolls
                document.location.hash = $('#navigation input.active').attr("data-roll");
            });
            
            // Looks for changes in the TN input box
            $(document).on('input', '#target', function() {
                calc_perc_and_avg($(this).val());
                $.data.rollDefaults['canvasOverlay']['objects'][0]['verticalLine']['x'] = $("#target").val();
                results_plot.plugins.canvasOverlay.get("target_number").options.x = $("#target").val();
                results_plot.replot();
            });
        
            // Looks for changes in the emphasis and explode check boxes
            $(document).on('change', '#emphasis, #explode, #explode_9_and_10', function() {
                calc_perc_and_avg($("#target").val());
                results_plot.series[0].data = $.data.plots[get_context()];
                results_plot.replot();
            });
            
            $.data.averages = {};
            $.data.plots = {};
            $.data.rollDefaults = {
                title: 'Graph',
                seriesDefaults:
                        {
                            fill: true,
                            color: "#123564",
                            shadow: false,
                        },
                grid:
                    {
                        background: "#fff",
                        borderColor: '#ddd',
                        borderWidth: 1.0,
                        gridLineColor: '#ddd',
                        shadow: false,
                    },
                canvasOverlay:
                            {
                                show: true,
                                objects: [
                                        {verticalLine: {
                                            name: "target_number",
                                            x: $("#target").val(),
                                            color: "#dbac6f",
                                            yOffset: 0,
                                            shadow: false,
                                            showTooltip: true,
                                            tooltipFormatString: "TN %'d",
                                            showTooltipPrecision: 0.5
                                        }},
                                        ]
                            },
                axesDefaults:
                            {
                                labelRenderer: $.jqplot.CanvasAxisLabelRenderer,
                                pad: 0, 
                                tickOptions:
                                            {
                                                formatString: "%d"
                                            },
                            },
                axes:
                    {
                        yaxis:
                            {
                                label: "%",
                                min: 0, 
                                ticks: [0,5,10,15,20,{value:23, showLabel:false, showMark:false, showGridline:false}],
                            },
                        xaxis:
                            {
                                label: "Result",
                                min: 1,
                                max: 110,
                                ticks: [1,5,10,15,20,25,30,35,40,45,50,
                                55,60,65,70,75,80,85,90,95,100,
                                105,110]
                            }
                    } // End axes settings
            } // End plot data & settings
            
            
            // Averages
            $.data.averages = {
<?php
                    foreach ($processed_results as $key=>$result) {
                        echo "\t\t\t\t'" , $key , "' : " , calculate_average($result, $sums[$key]) , ",\n";
                    }?>
                        };

            // Standard deviations
            $.data.std_deviations = {
<?php
                    foreach ($processed_results as $key=>$result) {
                        echo "\t\t\t\t'" , $key , "' : " , calculate_std_deviation($result, $sums[$key]) , ",\n";
                    }?>
                        };

            // Non emphasis
            $.data.plots = {
<?php
                    foreach ($processed_results as $key=>$result) {
                        echo "\t\t\t\t'" , $key , "' : " , build_array($result, $sums[$key]) , ",\n";
                    }?>
                        };


            // Builds the navigation buttons
            $.each($.data.plots, function(value) {

                var new_button = $('<input type="button">');
                $(new_button)
                    .val(value.replace("r", ""))
                    .attr("data-roll", value)
                    .attr("class", value);
                
                // Match each into a row for a nice navigation
                if (value.match(/k1$/)) {
                    $("#navigation #xk1").append(new_button);
                }
                
                else if (value.match(/k2$/)) {
                    $("#navigation #xk2").append(new_button);
                }

                else if (value.match(/k3$/)) {
                    $("#navigation #xk3").append(new_button);
                }
                
                else if (value.match(/k4$/)) {
                    $("#navigation #xk4").append(new_button);
                }

                else if (value.match(/k5$/)) {
                    $("#navigation #xk5").append(new_button);
                }
                
                else if (value.match(/k6$/)) {
                    $("#navigation #xk6").append(new_button);
                }

                else if (value.match(/k7$/)) {
                    $("#navigation #xk7").append(new_button);
                }
                
                else if (value.match(/k8$/)) {
                    $("#navigation #xk8").append(new_button);
                }
                
                else if (value.match(/k9$/)) {
                    $("#navigation #xk9").append(new_button);
                }
                
                else if (value.match(/k10$/)) {
                    $("#navigation #xk10").append(new_button);
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
            
            var results_plot = $.jqplot("chart", [$.data.plots[get_context()]], $.data.rollDefaults);
            results_plot.title.text = "Rolling " + $('#navigation input.active').attr("data-roll") + ', exploding dice';
            results_plot.replot();

        });
    </script>
</head>
<body>
    <div id="notice"><p class="no-js">This page contains interactive elements, to use it you need to activate JavaScript.</p></div>
    <div id="chart"></div>
    <div id="result">
        Probability to succeed is <span id="percent"></span>% and the average result is <span id="average"></span>
    </div>
    <form>
        <span class="tn_wrap"><label for="target" title="TN to hit">Target Number</label><input type="number" min="1" max="110" id="target" value="15"></span>
        <span class="options">
            <span class="check_wrap"><label for="emphasis">With emphasis</label><input type="checkbox" id="emphasis"></span>
            <span class="extra_options">
                <span class="check_wrap"><label for="explode">Explode</label><input type="checkbox" id="explode" checked></span>
                <span class="check_wrap"><label for="explode_9_and_10">Explode on 9 and 10</label><input type="checkbox" id="explode_9_and_10"></span>
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
