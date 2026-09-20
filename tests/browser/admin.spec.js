import { test, expect } from "@playwright/test";

test("administrator manages a user, API docs load, and CSRF is enforced", async ({
    page,
}) => {
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await page.goto("http://127.0.0.1:8017/login");
    await page.getByLabel("Email", { exact: true }).fill("admin@mtis.test");
    await page.getByLabel("Пароль", { exact: true }).fill("Mtis-Demo-2026!");
    await page.getByRole("button", { name: "Войти", exact: true }).click();
    await expect(page.locator(".page-heading")).toBeVisible();
    const csrf = await page.request.post(
        "http://127.0.0.1:8017/api/v1/issues",
        {
            data: { title: "CSRF rejection" },
            headers: { Accept: "application/json" },
        },
    );
    expect(csrf.status()).toBe(419);
    await page
        .locator(".sidebar")
        .getByRole("link", { name: "Администрирование", exact: true })
        .click();
    await page
        .getByRole("button", { name: "Пользователь", exact: true })
        .click();
    const unique = Date.now();
    const name = "Тестовый пользователь " + unique;
    const dialog = page.getByRole("dialog");
    await dialog.getByLabel("ФИО", { exact: true }).fill(name);
    await dialog
        .getByLabel("Учетная запись", { exact: true })
        .fill("test_" + unique);
    await dialog
        .getByLabel("Email", { exact: true })
        .fill("test_" + unique + "@mtis.test");
    await dialog.getByLabel("Пароль", { exact: true }).fill("Mtis-Test-2026!");
    await dialog
        .getByRole("checkbox", { name: "Цифровые сервисы", exact: true })
        .check();
    await dialog.getByRole("checkbox", { name: "Enbek", exact: true }).check();
    await dialog
        .getByRole("button", { name: "Сохранить пользователя", exact: true })
        .click();
    await expect(dialog).not.toBeVisible();
    const row = page.getByRole("row").filter({ hasText: name });
    await expect(row).toContainText("Активен");
    await row.click();
    await expect(
        dialog.getByRole("checkbox", { name: "Enbek", exact: true }),
    ).toBeChecked();
    await dialog
        .getByRole("checkbox", { name: "Учетная запись активна", exact: true })
        .uncheck();
    await dialog
        .getByRole("button", { name: "Сохранить пользователя", exact: true })
        .click();
    await expect(dialog).not.toBeVisible();
    await expect(row).toContainText("Отключен");
    await page.goto("http://127.0.0.1:8017/api-docs");
    await expect(
        page.getByRole("heading", { name: "MTIS API", exact: false }),
    ).toBeVisible();
    await expect(page.locator(".opblock").first()).toBeVisible();
    expect(errors).toEqual([]);
});
