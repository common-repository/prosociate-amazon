<html>
<head>
    <title></title>
</head>
<body style='font-family: Arial;'>

<center>
    <div style='font-size: 20px; font-weight: bold; text-align: center; width: 500px; margin-top: 200px;'>
        You must click the <img src='<?php echo PROSSOCIATE_ROOT_URL; ?>/images/continue.gif' style='vertical-align: middle;'> button on the next page to proceed.
    </div>
</center>

<div style='text-align: center; font-size: 14px;'>
    <p>You will now be redirected to Amazon. <a href="<?php echo $pxml->Cart->PurchaseURL; ?>">Click here to redirect now.</a></p>
</div>

<script type="text/javascript">
    window.setTimeout(function () {
        window.location.href = "<?php echo $pxml->Cart->PurchaseURL; ?>";
    }, 3000)
</script>
</body>
</html>