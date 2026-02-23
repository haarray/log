<?php

return [
    // Keep finance app domain code isolated from core reflection.
    'exclude_paths' => [
        'app/Http/Controllers/AccountController.php',
        'app/Http/Controllers/TransactionController.php',
        'app/Http/Controllers/PortfolioController.php',
        'app/Http/Controllers/SuggestionController.php',
        'app/Http/Controllers/TelegramWebhookController.php',
        'app/Models/Account.php',
        'app/Models/ExpenseCategory.php',
        'app/Models/Transaction.php',
        'app/Models/IPO.php',
        'app/Models/IpoPosition.php',
        'app/Models/GoldPosition.php',
        'app/Models/Suggestion.php',
        'app/Models/TelegramUpdate.php',
        'database',
        'resources/views/accounts',
        'resources/views/transactions',
        'resources/views/portfolio',
        'resources/views/suggestions',
        'routes/web.php',
        'routes/console.php',
        'README.md',
        'docs/tutorials/FINANCE_LOG_WORKFLOW.md',
    ],
];
