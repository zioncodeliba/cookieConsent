// Bottom position button
        echo '<div class="wpccm-position-option" style="text-align: center;">';
        echo '<input type="radio" name="wpccm_options[design][banner_position]" id="banner_position_bottom" value="bottom" ' . checked($banner_position, 'bottom', false) . ' style="display: none;" />';
        echo '<label for="banner_position_bottom" class="wpccm-position-button" style="display: block; width: 120px; height: 80px; border: 3px solid ' . ($banner_position === 'bottom' ? '#0073aa' : '#ddd') . '; border-radius: 8px; cursor: pointer; background: ' . ($banner_position === 'bottom' ? '#e7f3ff' : '#f9f9f9') . '; transition: all 0.3s ease; position: relative; overflow: hidden;">';
        echo '<div style="position: absolute; top: 0; left: 0; right: 0; height: 50px; background: ' . esc_attr($background_color) . '; border-bottom: 1px solid #dee2e6;"></div>';
        echo '<div style="position: absolute; top: 60px; left: 10px; width: 25px; height: 8px; background: #dc3545; border-radius: 4px;"></div>';
        echo '<div style="position: absolute; top: 60px; left: 47px; width: 25px; height: 8px; background: #6c757d; border-radius: 4px;"></div>';
        echo '<div style="position: absolute; top: 60px; left: 85px; width: 25px; height: 8px; background: #28a745; border-radius: 4px;"></div>';
        echo '</label>';
        echo '<div style="margin-bottom: 8px; font-weight: 500; color: ' . ($banner_position === 'bottom' ? '#0073aa' : '#666') . ';">בתחתית הדף</div>';
        echo '</div>';