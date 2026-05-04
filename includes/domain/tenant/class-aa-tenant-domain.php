<?php
/**
 * Canonical tenant identity rules.
 *
 * Espejo PHP de `utils/tenantDomain.js` del backend `deoia-oauth-backend`.
 * Define cómo se deriva el identificador canónico de un tenant a partir de
 * una `site_url`. Es una regla de dominio pura: no depende de WordPress,
 * no toca BD, y debe coincidir bit a bit con la del backend para que el
 * lookup de `agenda_clients` (OAuth + HMAC) reconozca el mismo tenant
 * tanto cuando lo crea provisioning como cuando se reconecta vía OAuth.
 *
 * Reglas (mismas que el backend):
 *   - host normalizado a minúsculas, sin `www.`
 *   - puerto incluido solo si es no estándar para el scheme (80 http, 443 https)
 *   - en producción con subdominio o dominio raíz: solo el host
 *       https://agenda-roby.deoia.com/  -> agenda-roby.deoia.com
 *       https://www.cliente.com/        -> cliente.com
 *   - en localhost con subdirectorio (multisite local) o dominios con path:
 *     se preserva la ruta completa (sin trailing slash)
 *       http://localhost/deoia-platform/agenda-roby/  -> localhost/deoia-platform/agenda-roby
 *       http://localhost/wpagenda/                    -> localhost/wpagenda
 *       http://localhost:8080/site-a/                 -> localhost:8080/site-a
 *       https://cliente.com/agenda/                   -> cliente.com/agenda
 *
 * No se usa para construir el `redirect_uri` ni el `state` de OAuth: solo
 * para identificar el tenant frente al backend.
 *
 * @package WPAgendaAutomatizada\Domain\Tenant
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('AA_Tenant_Domain')) {
    /**
     * Reglas puras de identidad del tenant a partir de una site_url.
     */
    class AA_Tenant_Domain {

        /**
         * Devuelve el dominio canónico que se almacena en
         * `agenda_clients.domain` y que viaja como `X-Client-Id`.
         *
         * @param string|null $site_url
         * @return string
         */
        public static function canonical($site_url) {
            if (!is_string($site_url) || trim($site_url) === '') {
                return 'default';
            }

            $parsed = parse_url(trim($site_url));
            if (!is_array($parsed) || empty($parsed['host'])) {
                return 'default';
            }

            $scheme = isset($parsed['scheme']) ? strtolower((string) $parsed['scheme']) : '';
            $host = strtolower((string) $parsed['host']);

            if (!empty($parsed['port'])) {
                $port = (int) $parsed['port'];
                $default_port = ($scheme === 'https') ? 443 : 80;
                if ($port !== $default_port) {
                    $host .= ':' . $port;
                }
            }

            if (strpos($host, 'www.') === 0) {
                $host = substr($host, 4);
            }

            $path = isset($parsed['path']) ? rtrim((string) $parsed['path'], '/') : '';

            if ($path !== '' && $path !== '/') {
                return $host . $path;
            }

            return $host;
        }

        /**
         * Devuelve la `site_url` con scheme/host en minúsculas y exactamente
         * un slash final. Sin query ni fragment. Útil cuando el backend
         * persiste `agenda_clients.site_url` y queremos enviar siempre la
         * misma forma.
         *
         * @param string|null $site_url
         * @return string|null
         */
        public static function normalize_site_url($site_url) {
            if (!is_string($site_url) || trim($site_url) === '') {
                return null;
            }

            $parsed = parse_url(trim($site_url));
            if (!is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
                return null;
            }

            $scheme = strtolower((string) $parsed['scheme']);
            $host = strtolower((string) $parsed['host']);

            $port_segment = '';
            if (!empty($parsed['port'])) {
                $port = (int) $parsed['port'];
                $default_port = ($scheme === 'https') ? 443 : 80;
                if ($port !== $default_port) {
                    $port_segment = ':' . $port;
                }
            }

            $path = isset($parsed['path']) ? rtrim((string) $parsed['path'], '/') : '';

            return $scheme . '://' . $host . $port_segment . $path . '/';
        }
    }
}
