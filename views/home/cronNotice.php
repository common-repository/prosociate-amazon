<?php
// Get cron URL
$cronUrl = site_url() . '/?proscron=' . get_option('prossociate_settings-dm-cron-api-key', '');
?>

<div class="wrap">
<h1>Prosociate: Cron Job</h1>

<p>
	Run this cron job every two minutes to keep your product data constantly updated. This cron job will update product data for existing products, post newly available products for any Subscriptions you have created.
</p>

<b>Cron URL</b>
<input type='text' readonly value='<?php echo $cronUrl; ?>' style='width: 500px;' /><br />
<small>Run this URL every 2 minutes</small>

<br /><br />
<b>Cron Command</b>
<input type='text' readonly value='wget -O /dev/null <?php echo $cronUrl; ?>' style='width: 700px;' /><br />
<small>To run the above URL on most web hosting providers, the above cron command will work.</small>


<p>
	Instructions for setting up a cron vary based on your web hosting provider. When in doubt, e-mail your web host and ask them to how to set up a cron job to run the above URL every 2 minutes.<br /><br />
	<b>Here's a video showing how to set up the cron job in cPanel:</b><br />
	<iframe width="560" height="315" src="//www.youtube.com/embed/bmBjg1nD5yA?rel=0" frameborder="0" allowfullscreen></iframe>

</p>

<p>
</p>

<h2 style='margin-bottom: 12px; margin-top: 24px;'>Common Questions</h2>

<p>
	<b>What is a cron job?</b><br />
	A cron job is a scheduled task that is run automatically in the background. Once you configure the cron job, Prosociate will automatically check for new product data in the background.
</p>

<p>
	<b>Won't this use a lot of server resources?</b><br />
	No, not at all. Just because the script runs every two minutes doesn't mean that it is constantly running. If all your product data is up to date, there's nothing for Prosociate to do, and the cron will stop immediately.
</p>

</div>
