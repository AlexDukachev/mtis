import { test, expect } from "@playwright/test";

test("dark theme keeps dividers subdued across workload, activity and issue details", async ({
    page,
}) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto("http://127.0.0.1:8017/login");
    await page.getByLabel("Email", { exact: true }).fill("admin@mtis.test");
    await page.getByLabel("Пароль", { exact: true }).fill("Mtis-Demo-2026!");
    await page.getByRole("button", { name: "Войти", exact: true }).click();
    await expect(page.locator(".loading-screen")).not.toBeVisible();
    await expect(page.locator(".workload-table")).toBeVisible();

    // Exercise CSS without changing the demo user's saved theme preference.
    const theme = async (value) =>
        page.evaluate((value) => {
            document.documentElement.dataset.theme = value;
        }, value);
    const checkBorder = async (selector, side, color) => {
        await expect(page.locator(selector).first()).toHaveCSS(
            `border-${side}-color`,
            color,
        );
    };
    await theme("light");
    await checkBorder(".attention-row:visible", "bottom", "rgb(240, 242, 237)");
    await checkBorder(".app-footer", "top", "rgb(240, 242, 237)");
    const employeeRow = page
        .locator(".workload-table .employee-row")
        .filter({ hasText: "Дмитрий Петров" });
    await employeeRow.click();
    await page
        .getByRole("heading", { name: "Загрузка команды", exact: true })
        .hover();
    await expect(employeeRow).toHaveCSS("background-color", "rgba(0, 0, 0, 0)");
    await theme("dark");
    await expect(employeeRow).toHaveCSS("background-color", "rgba(0, 0, 0, 0)");
    await employeeRow.hover();
    await expect(employeeRow).toHaveCSS("background-color", "rgba(0, 0, 0, 0)");
    await employeeRow.click();
    await expect(page.locator(".employee-detail")).not.toBeVisible();
    await employeeRow.press("Enter");
    await expect(page.locator(".employee-detail")).toBeVisible();
    await expect(employeeRow).toHaveCSS("background-color", "rgba(0, 0, 0, 0)");
    await expect(page.locator(".employee-detail")).toHaveCSS(
        "background-color",
        "rgba(0, 0, 0, 0)",
    );
    await expect(employeeRow.locator("td").first()).toHaveCSS(
        "box-shadow",
        "rgb(102, 199, 166) 2px 0px 0px 0px inset",
    );
    await expect(employeeRow).toHaveCSS("outline-color", "rgb(102, 199, 166)");
    const hoveredRow = page
        .locator(".workload-table .employee-row")
        .filter({ hasText: "Анна Смирнова" });
    await hoveredRow.click();
    await employeeRow.click();
    await hoveredRow.hover();
    await expect(hoveredRow).toHaveCSS("background-color", "rgba(0, 0, 0, 0)");
    await expect(employeeRow).toHaveCSS("background-color", "rgba(0, 0, 0, 0)");
    await page.screenshot({
        path: "storage/app/qa-dark-selected-row.png",
        fullPage: true,
        animations: "disabled",
    });
    await employeeRow.click();
    await checkBorder(".attention-row:visible", "bottom", "rgb(43, 51, 48)");
    await checkBorder(".app-footer", "top", "rgb(43, 51, 48)");
    await page.screenshot({
        path: "storage/app/qa-dark-dividers.png",
        fullPage: true,
        animations: "disabled",
    });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.locator(".app-footer").scrollIntoViewIfNeeded();
    await page.screenshot({
        path: "storage/app/qa-dark-dividers-mobile.png",
        animations: "disabled",
    });
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page
        .locator(".sidebar")
        .getByRole("link", { name: "Обзор", exact: true })
        .click();
    await checkBorder(".activity-row:visible", "bottom", "rgb(43, 51, 48)");
    await page
        .locator(".sidebar")
        .getByRole("link", { name: "Все задачи", exact: true })
        .click();
    for (const value of ["light", "dark"]) {
        await theme(value);
        await expect(page.locator(".search-field input")).toHaveCSS(
            "background-color",
            "rgba(0, 0, 0, 0)",
        );
        await expect(page.locator(".global-search input")).toHaveCSS(
            "background-color",
            "rgba(0, 0, 0, 0)",
        );
        await expect(page.locator(".search-field")).toHaveCSS(
            "background-color",
            value === "dark" ? "rgb(37, 43, 42)" : "rgb(255, 255, 255)",
        );
    }
    await page.locator(".search-field input").click();
    await checkBorder(".search-field", "top", "rgb(102, 199, 166)");
    await page.screenshot({
        path: "storage/app/qa-dark-search.png",
        animations: "disabled",
    });
    await page
        .getByRole("textbox", { name: "Поиск задач", exact: true })
        .fill("Исправить проверку БИН при регистрации");
    await expect(page.locator(".issue-table tbody tr")).toHaveCount(1);
    await page.locator(".issue-table tbody tr").click();
    await expect(page.getByRole("dialog")).toBeVisible();
    await checkBorder(".comment-form", "top", "rgb(53, 60, 58)");
    await checkBorder(
        ".comment-form > div:last-child",
        "top",
        "rgb(43, 51, 48)",
    );
    await checkBorder(".timeline-entry", "top", "rgb(43, 51, 48)");
    await checkBorder(".test-results:visible", "left", "rgb(53, 60, 58)");
    await expect(page.locator(".time-summary")).toHaveCSS(
        "background-color",
        "rgba(0, 0, 0, 0)",
    );
    await expect(page.locator(".time-summary strong").first()).toHaveCSS(
        "color",
        "rgb(225, 230, 227)",
    );
    await page.screenshot({
        path: "storage/app/qa-dark-issue-dividers.png",
        animations: "disabled",
    });
});
