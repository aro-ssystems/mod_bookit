<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Installation helper for mod_bookit.
 *
 * @package    mod_bookit
 * @copyright  2025 ssystems GmbH <oss@ssystems.de>
 * @author     Andreas Rosenthal
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local;

defined('MOODLE_INTERNAL') || die();

use mod_bookit\local\entity\bookit_checklist_master;
use mod_bookit\local\entity\bookit_checklist_category;
use mod_bookit\local\entity\bookit_checklist_item;

/**
 * Installation helper class.
 */
class install_helper {

    /**
     * Create default checklist data during installation.
     *
     * @param bool $force Force creation even if data exists
     * @param bool $verbose Print verbose output
     * @return bool True if data was created, false otherwise
     */
    public static function create_default_checklists(bool $force = false, bool $verbose = false): bool {
        global $DB;

        // Check if a master checklist already exists.
        $existing = $DB->count_records('bookit_checklist_master');
        if ($existing > 0 && !$force) {
            if ($verbose) {
                mtrace('Checklist data already exists. Skipping creation.');
            }
            return false;
        }

        if ($verbose) {
            mtrace('Creating default checklist data for BookIt...');
        }

        // Collection of cake-themed nonsense words for our test data.
        $cakewords = [
            'Frosting', 'Sprinkles', 'Buttercream', 'Fondant', 'Ganache',
            'Macaron', 'Tiramisu', 'Cupcake', 'Muffin', 'Tart',
            'Eclair', 'Croissant', 'Danish', 'Pastry', 'Donut',
            'Biscuit', 'Cookie', 'Brownie', 'Meringue', 'Gelato',
            'Whipple', 'Crumbly', 'Glazery', 'Frosticle', 'Sugarwhisk',
            'Creamify', 'Bakesome', 'Spongify', 'Doughmatic', 'Icingblast',
            'Velvetine', 'Frangipan', 'Confetti', 'Caramello', 'Bonbonita',
            'Chocochip', 'Truffler', 'Pralinux', 'Cinnapuff', 'Cakenstein',
            'Moussique', 'Frostique', 'Zestibite', 'Crèmewave', 'Sucrèlia',
            'Puddingo', 'Crinklefluff', 'Doughzen', 'Brûléator', 'Spongeworthy',
            'Fruitblast', 'Pipette', 'Wafflish', 'Biscotti', 'Crackleglaze',
            'Parfaitly', 'Soufflux', 'Cookielle', 'Tartiness', 'Shortcrumb'
        ];

        $descriptions = [
            'Swirled with vanilla frosting and rainbow sprinkles for extra flair',
            'Topped with chocolate ganache and a hint of sea salt',
            'Layered with strawberry jam and whipped cream cheese',
            'Dusted with powdered sugar and cinnamon spice',
            'Infused with lemon zest and honey glaze',
            'Marbled with caramel ribbons and toasted nuts',
            'Filled with rich custard and topped with fresh berries',
            'Drizzled with maple syrup and crispy bacon bits',
            'Swirled with peanut butter and chocolate chips',
            'Baked with golden butter and brown sugar crumble',
            'Topped with fluffy marshmallow and graham cracker pieces',
            'Glazed with espresso syrup and cocoa powder',
            'Stuffed with cream cheese and blueberry compote',
            'Frosted with minty buttercream and chocolate shavings',
            'Rolled in cinnamon sugar and filled with apple pie filling',
            'Garnished with candied violets and pistachio dust',
            'Soaked in rum syrup with caramelized banana pieces',
            'Swirled with dulce de leche and toasted coconut flakes',
            'Infused with lavender honey and topped with edible flowers',
            'Filled with passion fruit curd and white chocolate mousse',
            'Marbled with matcha green tea and black sesame paste',
            'Studded with ruby chocolate chunks and freeze-dried raspberries',
            'Layered with hazelnut praline and dark chocolate ganache',
            'Adorned with gold leaf and champagne-infused cream',
            'Decorated with spun sugar and crystallized ginger',
            'Rippled with salted caramel and crushed pretzel pieces',
            'Baked with chai spices and topped with orange blossom glaze',
            'Filled with mascarpone cream and amaretto-soaked cherries',
            'Dusted with cocoa nibs and smoked sea salt flakes',
            'Swirled with black forest cake flavors and cherry compote'
        ];

        // Create the master checklist.
        if ($verbose) {
            mtrace('Creating master checklist...');
        }

        $master = new bookit_checklist_master(
            null,
            'Cake Bakery Master Checklist',
            'A delicious collection of pastry-themed checklist items',
            1, // Make it the default
            []
        );
        $masterid = $master->save();

        if ($verbose) {
            mtrace("Created master checklist with ID: $masterid");
        }

        // Create categories and items.
        $categorynames = [
            'Frosted Delights',
            'Buttercream Bonanza',
            'Sprinkle Paradise',
            'Chocolatier\'s Dream',
            'Pastry Wonderland',
            'Confection Collection',
            'Baker\'s Delight',
            'Sweet Temptations',
            'Gourmet Treats'
        ];

        // Wähle zufällig 3-5 Kategorien für eine abwechslungsreichere Erfahrung
        shuffle($categorynames);
        $categorynames = array_slice($categorynames, 0, rand(3, 5));

        $itemtypes = [1, 2, 3, 4]; // 1=text, 2=number, 3=date, 4=dropdown

        foreach ($categorynames as $index => $categoryname) {
            if ($verbose) {
                mtrace("Creating category: $categoryname");
            }

            // Create category.
            $category = new bookit_checklist_category(
                null,
                $masterid,
                $categoryname,
                shuffle($descriptions) && $descriptions[0],
                [], // checklist items
                $index + 1 // sortorder
            );

            $categoryid = $category->save();
            if ($verbose) {
                mtrace("  Category ID: $categoryid");
            }

            // Erstelle eine zufällige Anzahl von Items (2-6) für jede Kategorie
            $itemCount = rand(2, 6);
            for ($i = 0; $i < $itemCount; $i++) {
                // Randomly generate a cake-themed name.
                $itemname = $cakewords[array_rand($cakewords)] . ' ' . $cakewords[array_rand($cakewords)];

                // Randomly select item type and description.
                $itemtype = $itemtypes[array_rand($itemtypes)];
                $desc = $descriptions[array_rand($descriptions)];

                // Create options based on item type.
                $options = null;
                if ($itemtype == 4) { // dropdown
                    $options = json_encode([
                        'options' => [
                            'Chocolate',
                            'Vanilla',
                            'Strawberry',
                            'Raspberry',
                            'Lemon'
                        ]
                    ]);
                }

                $defaultvalue = null;
                switch ($itemtype) {
                    case 1: // text
                        $defaultvalue = 'Sample text';
                        break;
                    case 2: // number
                        $defaultvalue = rand(1, 100);
                        break;
                    case 3: // date
                        $defaultvalue = time() + (rand(1, 30) * 86400); // Random date in the next 30 days.
                        break;
                    case 4: // dropdown
                        $defaultvalue = 'Vanilla';
                        break;
                }

                if ($verbose) {
                    mtrace("    Creating item: $itemname");
                }

                $item = new bookit_checklist_item(
                    0, // ID will be set by save_to_database
                    $masterid,
                    $categoryid,
                    null, // No parent
                    $desc,
                    $itemname,
                    $itemtype,
                    $options,
                    $i + 1, // sortorder
                    rand(0, 1), // is_required (randomly required or not)
                    $defaultvalue,
                    rand(-7, 14), // due_days_offset (between -7 and 14 days)
                    null,
                    null,
                    null
                );

                $itemid = $item->save();
                if ($verbose) {
                    mtrace("      Item ID: $itemid");
                }
            }
        }

        // Update the master checklist with the category IDs.
        $categories = $DB->get_records('bookit_checklist_category', ['masterid' => $masterid], 'sortorder ASC', 'id');
        $categoryids = array_keys($categories);

        $master->checklistcategories = $categoryids;
        $master->save();

        if ($verbose) {
            mtrace("Updated master checklist with category IDs: " . implode(', ', $categoryids));
            mtrace('Default checklist data created successfully!');
        }

        return true;
    }
}
