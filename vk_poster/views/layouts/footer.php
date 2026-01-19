        </div>
    </main>
    
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> VK Poster. Все права защищены.</p>
        </div>
    </footer>
    
    <script src="/vk_poster/assets/js/main.js"></script>
    <?php if (isset($additionalScripts)): ?>
        <?php foreach ($additionalScripts as $script): ?>
            <script src="<?php echo $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>