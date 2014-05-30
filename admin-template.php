<h1><?php echo __('2kb Performance'); ?></h1>
<div><?php echo __('Simple as shit, fast like lightening.'); ?></div>
<br/>

<?php if ($this->reload): ?>
<div><?php echo __('Reloading...'); ?></div>
<script>
    window.location.href = '<?php echo $_SERVER['REQUEST_URI']; ?>';
</script>
<?php return; endif; ?>



<?php if ($this->cssCachedFile): ?>
<div>
    <b><?php echo __('Cached CSS File Data'); ?> <br/></b>
</div>
<ul>
<?php foreach ($this->cssCachedFile['css'] as $css): ?>

    <li>
        <?php echo $css['url']; ?>
    </li>

<?php endforeach; ?>
</ul>
<div>
    <code><?php echo $this->cssCachedFile['url']; ?></code>
    <br/>
    <code><?php echo $this->cssCachedFile['size']; ?></code>
    <br/>
    <code><?php echo $this->cssCachedFile['date']; ?></code>
</div>

<?php endif; ?>

<hr/>

<?php if ($this->jsCachedFile): ?>
    <div>
        <b><?php echo __('Cached JS File Data'); ?> <br/></b>
    </div>
    <?php foreach ($this->jsCachedFile as $name => $jsData): ?>
        <div><?php echo $name; ?></div>
        <ul>
        <?php foreach ($jsData['js'] as $js): ?>

                <li><?php echo $js['url']; ?></li>

        <?php endforeach; ?>
        </ul>
        <div>
        <code><?php echo $jsData['url']; ?></code>
        <br/>
        <code><?php echo $jsData['size']; ?></code>
        <br/>
        <code><?php echo $jsData['date']; ?></code>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
<br/>
<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>" style="display: inline-block;">
    <input type="hidden" name="action" value="generateCacheFiles"/>
    <input type="submit" name="css" value="<?php echo __('Generate Cache Files'); ?>" class="button button-primary button-large"/>
</form>

<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>" style="display: inline-block;margin-left: 10px;">
    <input type="hidden" name="action" value="clearOptions"/>
    <input type="submit" name="clear" value="<?php echo __('Clear Cache Files'); ?>" class="button button-primary button-large"/>
</form>
