<?php
require_once DOL_DOCUMENT_ROOT . '/custom/wooctodoli/core/class/woocapi.class.php';

class WoocSync
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function doScheduledJob($parameters = '')
    {
        global $conf;

        $base = isset($conf->global->WOOCTODOLI_WOO_URL) ? $conf->global->WOOCTODOLI_WOO_URL : '';
        $key = isset($conf->global->WOOCTODOLI_WOO_CONSUMER_KEY) ? $conf->global->WOOCTODOLI_WOO_CONSUMER_KEY : '';
        $secret = isset($conf->global->WOOCTODOLI_WOO_CONSUMER_SECRET) ? $conf->global->WOOCTODOLI_WOO_CONSUMER_SECRET : '';

        if (empty($base) || empty($key) || empty($secret)) {
            dol_syslog('WoocToDoli: missing configuration', LOG_ERR);
            return 0;
        }

        $api = new WoocApi($base, $key, $secret);

        try {
            $orders = $api->listOrders(isset($conf->global->WOOCTODOLI_IMPORT_ORDER_STATUS) ? $conf->global->WOOCTODOLI_IMPORT_ORDER_STATUS : 'completed', 20, 1);
            if (is_array($orders)) {
                foreach ($orders as $order) {
                    try {
                        dol_syslog('WoocToDoli: found order ' . $order['id']);

                        // Ensure customer exists in Dolibarr
                        $billing = isset($order['billing']) ? $order['billing'] : array();
                        $email = isset($billing['email']) ? $billing['email'] : '';

                        if (!empty($email)) {
                            require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
                            $soc = new Societe($this->db);
                            $res = $soc->fetch(0, '', '', '', '', '', '', '', '', '', $email);
                            if ($res > 0) {
                                dol_syslog('WoocToDoli: found existing thirdparty id=' . $soc->id);
                            } else {
                                // Create new thirdparty
                                $name = '';
                                if (!empty($billing['company'])) $name = $billing['company'];
                                else $name = trim((($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? '')));

                                if (empty($name)) $name = 'WooCustomer-' . $order['id'];

                                $soc->name = $name;
                                $soc->client = 1;
                                $soc->email = $email;
                                $soc->address = $billing['address_1'] ?? '';
                                $soc->zip = $billing['postcode'] ?? '';
                                $soc->town = $billing['city'] ?? '';
                                $soc->phone = $billing['phone'] ?? '';

                                global $user;
                                $createRes = $soc->create($user);
                                if ($createRes >= 0) {
                                    dol_syslog('WoocToDoli: created thirdparty id=' . $soc->id);
                                } else {
                                    dol_syslog('WoocToDoli: failed to create thirdparty for order ' . $order['id'], LOG_ERR);
                                }
                            }
                        } else {
                            dol_syslog('WoocToDoli: order ' . $order['id'] . ' has no billing email, skipping', LOG_WARNING);
                        }

                        //  Create order in Dolibarr if not exists
                        $orderRefExt = 'woo_' . $order['id'];
                        require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
                        $commande = new Commande($this->db);
                        $existing = $commande->fetch(0, '', $orderRefExt);
                        if ($existing > 0) {
                            dol_syslog('WoocToDoli: order ' . $order['id'] . ' already imported as commande id=' . $commande->id);
                            continue;
                        }

                        // Prepare new commande
                        $commande->socid = $soc->id ?? 0;
                        $commande->date_commande = isset($order['date_created']) ? strtotime($order['date_created']) : time();
                        $commande->ref_ext = $orderRefExt;
                        $commande->note_private = 'Imported from WooCommerce order ' . $order['id'];

                        global $user;
                        $resCreateOrder = $commande->create($user);
                        if ($resCreateOrder < 0) {
                            dol_syslog('WoocToDoli: failed to create commande for woo order ' . $order['id'] . ' - error=' . $commande->error, LOG_ERR);
                            continue;
                        }
                        dol_syslog('WoocToDoli: created commande id=' . $commande->id . ' for woo order ' . $order['id']);

                        // Add lines by SKU
                        if (!empty($order['line_items']) && is_array($order['line_items'])) {
                            require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
                            foreach ($order['line_items'] as $li) {
                                $sku = $li['sku'] ?? '';
                                $label = $li['name'] ?? ($li['sku'] ?? 'Item');
                                $qty = isset($li['quantity']) ? (float) $li['quantity'] : 1;
                                $price = isset($li['price']) ? (float) $li['price'] : (isset($li['subtotal']) ? (float) $li['subtotal'] : 0);

                                $fk_product = 0;
                                if (!empty($sku)) {
                                    $product = new Product($this->db);
                                    $prodRes = $product->fetch(0, $sku);
                                    if ($prodRes > 0) {
                                        $fk_product = $product->id;
                                    }
                                }

                                // txtva not calculated here (0). You can extend to map taxes.
                                $txtva = 0;

                                $addRes = $commande->addline($label, $price, $qty, $txtva, 0, 0, $fk_product, 0, 0, 0, 'HT', 0, '', '', 0, -1, 0, 0, null, 0, '', array(), null, '', 0, '');
                                if ($addRes < 0) {
                                    $err = $commande->error ?: 'unknown';
                                    dol_syslog('WoocToDoli: failed to add line for product ' . $sku . ' on commande ' . $commande->id . ' - ' . $err, LOG_ERR);
                                }
                            }

                            // Recompute totals / fetch lines
                            $commande->fetch($commande->id);
                            dol_syslog('WoocToDoli: added lines for commande id=' . $commande->id);
                        }
                    } catch (Exception $e) {
                        // Log full exception for this order, but continue with next orders
                        dol_syslog('WoocToDoli: exception processing order ' . ($order['id'] ?? 'unknown') . ' - ' . $e->getMessage(), LOG_ERR);
                        dol_syslog($e->getTraceAsString(), LOG_ERR);
                        continue;
                    }
                }
            }
        } catch (Exception $e) {
            dol_syslog('WoocToDoli: sync error: ' . $e->getMessage(), LOG_ERR);
            dol_syslog($e->getTraceAsString(), LOG_ERR);
            // Return success to Cron UI if at least partial work done, otherwise -1
            return -1;
        }

        return 1;
    }
}
