import SwaggerUI from "swagger-ui-dist/swagger-ui-bundle.js";
import "swagger-ui-dist/swagger-ui.css";

SwaggerUI({
    url: "/openapi.json",
    dom_id: "#swagger-ui",
    deepLinking: true,
    requestInterceptor(request) {
        request.headers["X-CSRF-TOKEN"] = document.querySelector(
            'meta[name="csrf-token"]',
        ).content;
        request.credentials = "same-origin";
        return request;
    },
});
