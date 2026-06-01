/**
 * Learning Module - Guías / Aprendizaje
 *
 * Shell initializer for recommendations section (static markup in index.php).
 */

(function () {
    'use strict';

    function initLearningModule() {
        var root = document.getElementById('aa-learning-recommendations-root');
        if (!root) {
            return;
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLearningModule);
    } else {
        initLearningModule();
    }
})();
