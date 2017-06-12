<?php

class ukk_tickster_import {

    const UKK_TICKSTER_API_KEY = 'f7fdb0e70674d9a5';
    const UKK_TICKSTER_EVENTS_URL = 'http://www.tickster.com/sv/api/0.2/events/by/cmdt5xkmxt3tkz1';
    const UKK_TICKSTER_EVENT_URL = 'http://www.tickster.com/sv/api/0.2/events/';

    private $importedItems = array();

    /**
     * Perform GET on the API
     */
    public function getItems() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, self::UKK_TICKSTER_EVENTS_URL.'?take=100&sort=eventend&key='.self::UKK_TICKSTER_API_KEY);
        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result);
    }

    public function getItem($id) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, self::UKK_TICKSTER_EVENT_URL.$id.'?key='.self::UKK_TICKSTER_API_KEY);
        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result);
    }


    /**
     * Insert new item
     */
    private function insertItem($itemData) {

        $post_id = wp_insert_post(array(
            'post_type' => 'event',
            'post_title' => (string) $itemData->name,
            'post_content' => html_entity_decode((string) $itemData->description),
            'post_status' => 'publish',
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        ));

        return $post_id;
        
    }


    /**
     * Update existing item
     */
    private function updateItem($postId, $itemData) {

        $post_id = wp_update_post(array(
            'ID' => $postId,
            'post_title' => (string) $itemData->name,
            'post_content' => html_entity_decode((string) $itemData->description)
        ));

        return $post_id;

    }


    /**
     * Update the item meta
     */
    private function updateItemMeta($postId, $remoteId, $itemData) {

        // Store Tickster ID
        update_post_meta( $postId, 'ukk_tickster_id', (string) $remoteId);


        // Store meta data
        if (!function_exists('get_field')) {
            echo "<p>Denna funktion kräver <strong>Advanced Custom Fields</strong>.</p>";

        } else {

            // Call to action - Text
            if (!get_field('call_to_action_text', $postId)) {
                update_field('call_to_action_text', 'Köp biljett direkt', $postId);
            }

            // Call to action - Link
            update_field('call_to_action_link', (string) $itemData->event->shopUri, $postId);

            // Occurrences
            update_field('occurrences', array(), $postId);
            if (count($itemData->childEvents) == 0) {
                // One time event, set dates as is
                $occurrence = array(
                    'startdate' => substr(str_replace('T', ' ', (string) $itemData->event->start), 0, 19),
                    'enddate' => substr(str_replace('T', ' ', (string) $itemData->event->end), 0, 19),
                    'url' => (string) $itemData->event->shopUri
                    );
                add_row('occurrences', $occurrence, $postId);
                // Save duration, goods node and venue for later
                $duration = strtotime((string) $itemData->event->end) 
                    - strtotime((string) $itemData->event->start);
                $goods_node = $itemData->event->goods;
                $venue = (string) $itemData->venue->name;
            } else {
                // Multiple occurrances
                foreach ($itemData->childEvents as $childEvent) {
                    // Avoid importing this child event separately later in the loop
                    array_push($this->importedItems, (string) $childEvent->id); 
                    // Store data for child event
                    $occurrence = array(
                        'startdate' => substr(str_replace('T', ' ', (string) $childEvent->start), 0, 19),
                        'enddate' => substr(str_replace('T', ' ', (string) $childEvent->end), 0, 19),
                        'url' => (string) $childEvent->shopUri
                        );
                    add_row('occurrences', $occurrence, $postId);
                    // Save duration, goods node and venue for later
                    $duration = strtotime((string) $childEvent->end) 
                        - strtotime((string) $childEvent->start);
                    $goods_node = $childEvent->goods;
                    $venue = (string) $childEvent->venue->name;
                }
            }

            // Duration - Text
            if (!get_field('duration_text', $postId)) {
                $h = floor($duration / 3600);
                $m = floor(($duration % 3600) / 60);
                if ($h > 0 && $m >0) {
                    $duration_text = "Cirka $h timmar och $m minuter";
                } else if ($h > 0) {
                    $duration_text = "Cirka $h timmar";
                } else {
                    $duration_text = "Cirka $m minuter";
                } 
                update_field('duration_text', $duration_text, $postId);
            }

            // Prices - Text
            $prices_text = "";
            foreach ($goods_node as $price) {
                $prices_text .= (string) $price->name . ': ' . $price->price->includingVat . ' kronor<br />';
            }
            update_field('price_html', $prices_text, $postId);

            // Organizer - Text
            if (!get_field('organizer_text', $postId)) {
                update_field('organizer_text', (string) $itemData->organizer->name, $postId);
            }

            // Venue - Text
            if (!get_field('venue_text', $postId)) {
                update_field('venue_text', $venue, $postId);
            }

            // Performers - Text
            $performers_text = "";
            foreach ($itemData->event->performers as $performer) {
                $performers_text .= (string) $performer . ', ';
            }
            $performers_text = rtrim($performers_text, ', ');
            update_field('performers_text', $performers_text, $postId);

        }

        // Terms
        if (isset($itemData->event->tags)) {
            $terms = array();
            foreach ($itemData->event->tags as $tag) {
                array_push($terms, (string) $tag);
            }
            wp_set_object_terms( $postId, $terms, 'tag' );
        }

    }


    /**
     * Delete all items
     */
    public function deleteAllItems() {
        $items = get_posts(array('post_type' => 'event', 'posts_per_page' => -1));
        foreach ($items as $item) {
            wp_delete_post($item->ID, true);
        }
    }


    /**
     * Get item by ID
     */
    private function getByItemId($id, $array) {
        foreach ($array as $key => $val) {
            if ($array[$key]->ukk_tickster_id === $id) {
                return $val->ID;
            }
        }
        return null;
    }



    /**
     * Refresh items from remote
     */
    public function refresh() {

        echo "<p>Hämtar data från Tickster...</p>";

        $remoteData = $this->getItems();

        if (count($remoteData) <= 0) {

            // Empty result from server
            echo "<p>Ett fel uppstod och evenemangen kunde inte läsas in.</p>";

        } else {
            // Uncomment below to delete all items during initial testing
            //$this->deleteAllItems();

            // Delete old items that do not exist on the remote
            $args = array('post_type' => 'event', 'posts_per_page' => -1, 'post_status' => 'any');
            $localItems = get_posts($args);

            foreach ($localItems as $localItem) {
                $remoteId = get_post_meta($localItem->ID, 'ukk_tickster_id', true);

                if (!empty($remoteId)) {

                    $found = false;
                    foreach ($remoteData->hits as $itemData) {
                        $itemId = (string) $itemData->id;
                        if ((string) $itemId == $remoteId) {
                            $found = true;
                            break;
                        }
                    }
                    
                    if (!$found) {
                        wp_trash_post($localItem->ID);

                        // Log id:s that were deleted for debugging
                        $fp = fopen( dirname(__FILE__) . '/' . 'deleted_items.txt', 'a');
                        fwrite( $fp, $remoteId."\t".date("Y-m-d H:i:s")."\n");
                        fclose( $fp );
                    }

                }
            }

            // Reverse array, because we want collection events first
            $remoteEvents = array_reverse($remoteData->hits);

            // Create new or update existing
            echo "<p>Importerar hämtat data...</p>";

            foreach ($remoteEvents as $itemData) {

                $itemId = (string) $itemData->id;

                if ( !in_array ($itemId, $this->importedItems) ) {

                    $itemData = $this->getItem($itemId);

                    $postId = $this->getByItemId((string) $itemId, $localItems);
                    if ($postId == null) {
                        $postId = $this->insertItem($itemData->event);
                    } else {
                        $this->updateItem($postId, $itemData->event);
                    }
                    $this->updateItemMeta($postId, $itemId, $itemData);

                }

            }

            update_option( 'ukk_tickster__last_update', current_time('timestamp') );

        }
        
    }

}

?>