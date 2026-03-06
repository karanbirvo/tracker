<?php
// This is not a full page, but a template file.
// It expects certain variables to be available when it's included.
// - $email_subject
// - $email_body_content
// - $email_footer_html
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($email_subject) ?></title>
    <style>
        /* Basic responsive styles for email clients that support them */
        @media screen and (max-width: 600px) {
            .content-table {
                width: 100% !important;
            }
        }
        div[style*="background-color:#FFEB9C"] {
  display: none !important;
}

    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f7f6;">
    <table width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#f4f7f6">
        <tr>
            <td align="center" style="padding: 20px 0;">
                
                <!-- Main Email Wrapper Table (600px wide) -->
                <table class="content-table" width="600" border="0" cellpadding="0" cellspacing="0" align="center" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                    
                    <!-- Header Section -->
                    <tr>
                        <td style="padding: 30px 40px; border-bottom: 1px solid #eeeeee;">
                            <!-- Your company logo -->
                            <img src="https://virtualoplossing.com/wp-content/themes/blankslate/assets/img/virtual-oplossing-logo.svg" alt="VirtualOplossing Logo" width="180" style="display: block;">
                        </td>
                    </tr>
                    
                    <!-- Body Content Section -->
                    <tr>
                        <td style="padding: 40px 40px 30px 40px; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 1.6; color: #333;">
                            
                            <!-- This is where the dynamic EOD report content will be injected -->
                            <?= $email_body_content ?>
                            
                        </td>
                    </tr>
                    
                    <!-- Footer / Signature Section -->
                    <tr>
                        <td style="padding: 0;">
                            
                            <!-- This is where your custom signature from the database will be injected -->
                            <?= $email_footer_html ?>
                            
                        </td>
                    </tr>
                    
                </table>

                <!-- Unsubscribe/Info Footer -->
                <table class="content-table" width="600" border="0" cellpadding="0" cellspacing="0" align="center" style="padding-top: 20px;">
                    <tr>
                        <td align="center" style="font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #999;">
                            <p style="margin: 0;">This email was sent automatically from the VO TimeTracker application.</p>
                            <p style="margin: 5px 0 0 0;">VirtualOplossing | 160072, Mohali, Punjab, India</p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
    <script><script>
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll("div").forEach(function (el) {
    if (el.textContent.includes("This email originated from outside of the organization")) {
      el.style.display = "none";
    }
  });
});
</script>
</script>
</body>
</html>