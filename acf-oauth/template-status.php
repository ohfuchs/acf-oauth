<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title><?php echo $status['title']; ?></title>
    <link href="<?php echo plugin_dir_url( __FILE__ ); ?>/assets/css/status-page.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Lato:300,700" rel="stylesheet">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css"/>
  </head>
  <body class="oauth-status-page <?php echo $status['type']; ?>">

    <div class="status">

      <div class="status-icon">

        <i class="fa <?php echo $status['type'] !== 'error' ? 'fa-check' : 'fa-frown-o'; ?>"></i>

      </div>

      <p class="status-message"><?php echo $status['message']; ?></p>

      <p class="status-close"><?php _e( 'You can close this window now', 'acf-oauth' ); ?></p>

      <?php if( $debug ): ?>

        <div class="debug">

          <textarea id="debug-log"><?php echo $debug; ?></textarea>

        </div>

      <?php endif; ?>
    </div>

    <?php if( $debug ): ?>

      <a class="show-debug" onclick="document.getElementById('debug-log').style.display='block'; this.style.display='none';">

        <i class="fa fa-toggle-on"></i>

        <?php echo __( 'Show Debug Info', 'acf-oauth' ); ?>

      </a>

    <?php endif; ?>

  </body>
</html>