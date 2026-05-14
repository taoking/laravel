import 'swagger-ui-dist/swagger-ui.css';
import SwaggerUIBundle from 'swagger-ui-dist/swagger-ui-bundle';
import SwaggerUIStandalonePreset from 'swagger-ui-dist/swagger-ui-standalone-preset';

window.addEventListener('load', () => {
    const container = document.getElementById('swagger-ui');

    SwaggerUIBundle({
        dom_id: '#swagger-ui',
        url: container?.dataset.openapiUrl ?? '/docs/openapi.yaml',
        presets: [
            SwaggerUIBundle.presets.apis,
            SwaggerUIStandalonePreset,
        ],
        layout: 'BaseLayout',
        deepLinking: true,
    });
});
