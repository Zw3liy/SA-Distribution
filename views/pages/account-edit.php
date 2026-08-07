<?php
declare(strict_types=1);

/**
 * @var array $appConfig
 * @var \App\Domains\Identity\Models\User $user
 * @var \App\Domains\Customers\Models\Address[] $addresses
 * @var string $error
 * @var string $flashMessage
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit profile | <?= esc($appConfig['name']); ?></title>
    <meta name="description" content="Update your SA Business Distribution account details.">
    <link href="css/styles.css" rel="stylesheet">
</head>
<body class="bg-light text-dark font-sans">
    <?php include APP_BASE_PATH . '/components/header.php'; ?>
    <main class="container page-content auth-page">
        <section class="auth-card">
            <h1>Edit account details</h1>
            <p>Keep your contact and company information up to date.</p>

            <?php if ($flashMessage): ?>
                <div class="flash-message success"><?= esc($flashMessage); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="flash-message error"><?= esc($error); ?></div>
            <?php endif; ?>

            <form method="post" action="account-edit.php" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">

                <label for="first_name">First name</label>
                <input id="first_name" name="first_name" type="text" value="<?= esc($user->firstName); ?>" required>

                <label for="last_name">Last name</label>
                <input id="last_name" name="last_name" type="text" value="<?= esc($user->lastName); ?>" required>

                <label for="company_name">Company</label>
                <input id="company_name" name="company_name" type="text" value="<?= esc($user->companyName ?? ''); ?>">

                <label for="phone">Phone number</label>
                <input id="phone" name="phone" type="tel" value="<?= esc($user->phone); ?>" required>

                <label for="password">New password</label>
                <input id="password" name="password" type="password" placeholder="Leave blank to keep current password">

                <label for="password_confirm">Confirm new password</label>
                <input id="password_confirm" name="password_confirm" type="password" placeholder="Leave blank to keep current password">

                <div class="checkbox-field">
                    <label><input type="checkbox" name="notifications_updates" <?= $user->notificationsUpdates ? 'checked' : ''; ?>> Receive product updates</label>
                </div>

                <button class="btn-primary" type="submit">Save changes</button>
            </form>

            <p class="auth-helper"><a href="account-dashboard.php">Back to dashboard</a></p>
        </section>

        <section class="auth-card">
            <h2>Your addresses</h2>
            <p>Manage billing and shipping addresses for your account.</p>

            <?php if (empty($addresses)): ?>
                <p>You haven't added any addresses yet.</p>
            <?php else: ?>
                <ul class="address-list">
                    <?php foreach ($addresses as $address): ?>
                        <li class="address-card">
                            <div class="address-card-header">
                                <strong><?= esc($address->label); ?></strong>
                                <span class="badge"><?= esc(ucfirst($address->type)); ?></span>
                                <?php if ($address->isDefault): ?>
                                    <span class="badge badge-default">Default</span>
                                <?php endif; ?>
                            </div>
                            <p>
                                <?= esc($address->addressLine1); ?><br>
                                <?php if ($address->addressLine2): ?><?= esc($address->addressLine2); ?><br><?php endif; ?>
                                <?= esc($address->city); ?>, <?= esc($address->region); ?> <?= esc($address->postalCode); ?><br>
                                <?= esc($address->country); ?>
                            </p>
                            <?php if (!$address->isDefault): ?>
                                <form method="post" action="account-edit.php" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
                                    <input type="hidden" name="form_action" value="set_default_address">
                                    <input type="hidden" name="address_id" value="<?= esc((string) $address->id); ?>">
                                    <input type="hidden" name="type" value="<?= esc($address->type); ?>">
                                    <button class="btn-secondary" type="submit">Set as default</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h3>Add a new address</h3>
            <form method="post" action="account-edit.php" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
                <input type="hidden" name="form_action" value="add_address">

                <label for="label">Label</label>
                <input id="label" name="label" type="text" placeholder="e.g. Head Office, Warehouse" required>

                <label for="address_line_1">Address line 1</label>
                <input id="address_line_1" name="address_line_1" type="text" required>

                <label for="address_line_2">Address line 2</label>
                <input id="address_line_2" name="address_line_2" type="text">

                <label for="city">City</label>
                <input id="city" name="city" type="text" required>

                <label for="region">Province</label>
                <input id="region" name="region" type="text" required>

                <label for="postal_code">Postal code</label>
                <input id="postal_code" name="postal_code" type="text" pattern="\d{4}" title="South African postal codes are 4 digits" required>

                <label for="country">Country</label>
                <input id="country" name="country" type="text" value="South Africa">

                <label for="type">Address type</label>
                <select id="type" name="type" required>
                    <option value="shipping">Shipping</option>
                    <option value="billing">Billing</option>
                    <option value="both">Billing &amp; shipping</option>
                </select>

                <div class="checkbox-field">
                    <label><input type="checkbox" name="is_default" value="1"> Set as default address for this type</label>
                </div>

                <button class="btn-primary" type="submit">Add address</button>
            </form>
        </section>
    </main>
</body>
</html>
