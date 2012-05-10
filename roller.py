#! /usr/bin/env python3.2.3
import random, types, shelve
from collections import defaultdict

# 550M per XkY + exploderade
iterations = 10000000
current_iteration = 1
saved_data = []
count_max = 150
count_min = 1
counter_dict = defaultdict(int)

def roll_function():
    rolled_raw = input("Roll ")
    try:
        rolled_raw = int(rolled_raw)
        if isinstance(rolled_raw, int):
            return rolled_raw
    except (ValueError, TypeError):
        print("No value given, assuming 5")
        return 5
        
def keep_function():
    kept_raw = input("Keep ")
    try:
        kept_raw = int(kept_raw)
        if isinstance(kept_raw, int):
            return kept_raw
    except (ValueError, TypeError):
        print("No value given, assuming 3")
        return 3

def emphasis_function():
    emphasis_raw = input("Has emphasis ")
    if emphasis_raw == "y":
        return emphasis_raw
    else:
        print("No value given, assuming no")
        return "n"
        
def explode_on_function():
    explode_on_raw = input("Explode on this or better ")
    try:
        explode_on_raw = int(explode_on_raw)
        if explode_on_raw == 10:
            return [10]
        else:
            return [explode_on_raw, 10]
    except (ValueError, TypeError):
        print("No value given, assuming 10")
        return [10]

def will_explode_function():
    will_explode_raw = input("Should dice explode ")
    if will_explode_raw == "y":
        return will_explode_raw
    else:
        print("No value given, assuming no")
        return "n"


to_roll = roll_function()
to_keep = keep_function()
has_emphasis = emphasis_function()

# If there will be no exploding, we do not need to ask what to explode on
will_explode = will_explode_function()
if will_explode == "y":
    explode_on = explode_on_function()
else:
    explode_on = [10]
  
# Never keep more than we roll
if to_keep > to_roll:
    to_keep = to_roll

while current_iteration <= iterations:
    rolls = 1
    current_roll = []
    # Starts a roll here
    while rolls <= to_roll:
        new_rand = 0
        continue_rolling = True
        rand_roll = []
        rand_roll.append(random.randint(1, 10))
        
        # Test if we should re-roll once if there is an active emphasis
        if rand_roll == [1]:
            if has_emphasis == "y":
                rand_roll[0] = random.randint(1, 10)
        
        # Explode and append
        if rand_roll[0] in explode_on and will_explode == "y":
            new_rand = rand_roll
            while continue_rolling == True:
                new_rand.append(random.randint(1, 10))
                # If last appended roll was an explosion, continue
                if new_rand[-1] in explode_on:
                    continue_rolling = True
                else:
                    continue_rolling = False
            
            rand_roll = new_rand
            
        current_roll.append(rand_roll)
        rolls = rolls+1
        # Ends the roll here

    # Sort each roll and keep only the best    
    current_roll.sort()
    current_roll.reverse()
    current_roll = current_roll[0:to_keep]

    # Calculate sums for each die
    new_saved_roll = []
    for dice in current_roll:
        new_saved_dice = sum(dice)
        new_saved_roll.append(new_saved_dice)

    # Sort the individual roll top to bottom
    new_saved_roll.sort()
    new_saved_roll.reverse()

    # Create sums for each list and add them to a dictionary
    the_sum = sum(new_saved_roll)
    if the_sum in counter_dict:
        counter_dict[the_sum] = counter_dict[the_sum]+1
    else:
        counter_dict[the_sum] = 1
    
    current_iteration = current_iteration+1

# Print this to the terminal
while count_min <= count_max:
    if count_min in counter_dict:
        print('"', to_roll, '","', to_keep, '","', count_min, '","', counter_dict[count_min], '";')
    else:
        print('"', to_roll, '","', to_keep, '","', count_min, '","0";')
    count_min = count_min+1