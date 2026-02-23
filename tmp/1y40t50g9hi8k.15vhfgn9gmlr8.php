<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <title><?= ($title ?: 'Mon site') ?></title>
    <link rel="stylesheet" href="../assets/style.css"/>
    <noscript>
        <link rel="stylesheet" href="<?= ($BASE) ?>/assets/css/noscript.css"/>
    </noscript>
    <!-- Mets ici tout autre <meta>, favicon, etc. du template -->
</head>
<body class="landing is-preload">
<!-- Page Wrapper -->
<div id="page-wrapper">
    <!-- Header / Nav -->
    <?php echo $this->render('partials/header.html',NULL,get_defined_vars(),0); ?>
    <!-- Contenu de page -->
    <?= ($this->raw($content))."
" ?>
    <!-- Footer -->
    <?php echo $this->render('partials/footer.html',NULL,get_defined_vars(),0); ?>
</div>
<!-- Scripts du template -->
<!--<script src="<?= ($BASE) ?>/assets/js/jquery.min.js"></script>-->
<!--<script src="<?= ($BASE) ?>/assets/js/jquery.scrollex.min.js"></script>-->
<!--<script src="<?= ($BASE) ?>/assets/js/jquery.scrolly.min.js"></script>-->
<!--<script src="<?= ($BASE) ?>/assets/js/browser.min.js"></script>-->
<!--<script src="<?= ($BASE) ?>/assets/js/breakpoints.min.js"></script>-->
<!--<script src="<?= ($BASE) ?>/assets/js/util.js"></script>-->
<!--<script src="<?= ($BASE) ?>/assets/js/main.js"></script>-->
<script src="../assets/script.js"></script>
</body>
</html>