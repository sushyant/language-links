jQuery(document).ready(function($) {
    $(document).on('click', '.upload-icon-button', function(e) {
        e.preventDefault();
        var button = $(this);
        var target = button.closest('td').find('.language_icon');

        var frame = wp.media({
            title: 'Select or Upload an SVG',
            button: {
                text: 'Use this SVG'
            },
            library: {
                type: 'image/svg+xml'
            },
            multiple: false
        });

        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            target.val(attachment.url);
        });

        frame.open();
    });

    $('#add-language-button').click(function() {
        var languageIndex = $('.language-link').length + 1;
        var newLanguageHtml = `
        <div class="language-link">
            <h2>Language ${languageIndex}</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <label>Language Field Label:</label>
                    </th>
                    <td><input type="text" name="language_label[]" value="" style="width: 100%;" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label>Language Icon (SVG URL):</label>
                    </th>
                    <td>
                        <input type="text" name="language_icon[]" class="language_icon" value="" style="width: 70%;" />
                        <button type="button" class="button upload-icon-button">Upload Icon</button>
                    </td>
                </tr>
            </table>
            <button type="button" class="button remove-language-button">Remove Language</button>
        </div>`;
        $('#language-links-container').append(newLanguageHtml);
        updateLanguageHeadings();
    });

    $(document).on('click', '.remove-language-button', function() {
        $(this).closest('.language-link').remove();
        updateLanguageHeadings();
    });

    function updateLanguageHeadings() {
        $('.language-link').each(function(index) {
            $(this).find('h2').text('Language ' + (index + 1));
        });
    }
});
