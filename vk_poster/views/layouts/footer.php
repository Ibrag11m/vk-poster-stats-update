<!-- vk_poster/views/layouts/footer.php -->
        </div> <!-- Закрытие .container -->
    </main> <!-- Закрытие .main -->

    <!-- Подключение jQuery и других основных скриптов через CDN перед закрытием body -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Если у вас есть основной JS проекта, подключите его локально -->
    <!-- <script src="/vk_poster/assets/js/main.js"></script> -->

    <!-- Подключение скриптов, специфичных для страницы (через $additionalScripts) -->
    <?php if (isset($additionalScripts) && is_array($additionalScripts)): ?>
        <?php foreach ($additionalScripts as $script): ?>
            <script src="<?php echo $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>