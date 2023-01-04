<?php
/** 
 * 
 * Provide a admin area view for the plugin
 *
 * This file is used to Sync Features.
 *
 * @link       https://instawp.com/
 * @since      1.0
 *
 * @package    instaWP
 * @subpackage instaWP/admin/instawp-change-event
 */

class InstaWP_Sync_Features {
  
    public function sync_features(){
        $attachment_id = 7840;
        wp_delete_attachment($attachment_id,true);
       
        $html = '<div class="sync-features">
                    <h2>SYNC FEATURES</h2>
                        <h3>Post</h3>    
                        <ul>
                            <li>Post create</li>
                            <li>Post update</li>
                            <li>Post trash</li>
                            <li>Post delete</li>
                            <li>Post restore</li>
                        </ul>
                        <h3>Plugin</h3>
                        <ul>
                            <li>Plugin activate</li>
                            <li>Plugin deactivate</li>
                        </ul>
                </div>';
        echo $html;
    }
}
$obj_sync_features = new InstaWP_Sync_Features();
$obj_sync_features->sync_features();