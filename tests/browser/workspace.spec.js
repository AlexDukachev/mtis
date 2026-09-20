import { test, expect } from "@playwright/test";

const base = "http://127.0.0.1:8017";

test("workspace renders, filters, opens issues and fits desktop and mobile", async ({
    page,
}) => {
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto(base);
    await page.getByLabel("Email", { exact: true }).fill("admin@mtis.test");
    await page.getByLabel("Пароль", { exact: true }).fill("Mtis-Demo-2026!");
    await page.getByRole("button", { name: "Войти", exact: true }).click();
    await expect(
        page.getByRole("heading", { name: "Загрузка команды", exact: true }),
    ).toBeVisible();
    await expect(
        page
            .locator(".workload-table")
            .getByText("Иван Иванов", { exact: true }),
    ).toBeVisible();
    await expect(page.locator(".loading-screen")).not.toBeVisible();
    await page.screenshot({
        path: "storage/app/qa-desktop.png",
        fullPage: true,
        animations: "disabled",
    });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    ).toBeTruthy();
    await page
        .getByRole("button", { name: "Задачи: Дмитрий Петров", exact: true })
        .click();
    await expect(page.locator(".employee-detail")).toBeVisible();
    const selectedRow = page.locator(".employee-row.row-selected");
    await expect(selectedRow).toContainText("Дмитрий Петров");
    await expect(
        selectedRow
            .locator("xpath=following-sibling::tr[1]")
            .locator(".employee-detail"),
    ).toBeVisible();
    await expect(
        page.getByRole("region", {
            name: "Задачи сотрудника: Дмитрий Петров",
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page.getByRole("button", {
            name: "Задачи: Дмитрий Петров",
            exact: true,
        }),
    ).toHaveAttribute("aria-expanded", "true");
    await page.screenshot({
        path: "storage/app/qa-inline-employee.png",
        fullPage: true,
        animations: "disabled",
    });
    await page
        .getByRole("button", { name: "Свернуть задачи", exact: true })
        .click();
    await expect(page.locator(".employee-detail")).toHaveCount(0);
    await expect(selectedRow).toHaveCount(0);
    await expect(
        page.locator(".employee-row").filter({ hasText: "Дмитрий Петров" }),
    ).toBeFocused();
    await page
        .getByRole("button", { name: "Задачи: Дмитрий Петров", exact: true })
        .click();
    await page
        .locator(".employee-detail")
        .getByRole("button")
        .filter({ hasText: "Исправить проверку БИН" })
        .click();
    await expect(page.getByRole("dialog")).toBeVisible();
    await expect(
        page.getByRole("heading", {
            name: "Исправить проверку БИН при регистрации",
        }),
    ).toBeVisible();
    await expect(
        page.getByText(
            "При вводе БИН с ведущим нулем система отклоняет корректное значение. Необходимо сохранить строковый формат.",
        ),
    ).toBeVisible();
    await page.screenshot({
        path: "storage/app/qa-issue.png",
        fullPage: false,
        animations: "disabled",
    });
    await page
        .getByRole("button", { name: "Закрыть окно", exact: true })
        .click();
    await page.getByRole("link", { name: "Все задачи", exact: true }).click();
    await page
        .getByRole("textbox", { name: "Поиск задач", exact: true })
        .fill("Перенос предприятия");
    await expect(page.locator(".issue-table tbody tr")).toHaveCount(1);
    await page
        .getByRole("button", { name: "Создать задачу", exact: true })
        .click();
    await expect(page.getByRole("dialog")).toBeVisible();
    await page
        .getByRole("button", { name: "Закрыть окно", exact: true })
        .click();
    for (const name of [
        "Обзор",
        "Проекты",
        "Команда",
        "Отчеты",
        "Уведомления",
        "Администрирование",
    ]) {
        await page
            .locator(".sidebar")
            .getByRole("link", { name, exact: name !== "Уведомления" })
            .click();
        await expect(
            page.getByRole("heading", { name, exact: true }).first(),
        ).toBeVisible();
        await expect(page.locator(".page-error")).not.toBeVisible();
    }
    await page
        .getByRole("link", { name: "Загрузка команды", exact: true })
        .click();
    await page.setViewportSize({ width: 390, height: 844 });
    await page
        .getByRole("button", { name: "Задачи: Дмитрий Петров", exact: true })
        .click();
    const mobileDetail = page.getByRole("region", {
        name: "Задачи сотрудника: Дмитрий Петров",
        exact: true,
    });
    await expect(mobileDetail).toBeVisible();
    await expect
        .poll(async () => {
            const bounds = await mobileDetail.boundingBox();
            return bounds && bounds.x >= 0 && bounds.x + bounds.width <= 390;
        })
        .toBeTruthy();
    await page.screenshot({
        path: "storage/app/qa-inline-employee-mobile.png",
        animations: "disabled",
    });
    await page
        .getByRole("button", { name: "Свернуть задачи", exact: true })
        .click();
    await page.screenshot({
        path: "storage/app/qa-mobile.png",
        fullPage: true,
        animations: "disabled",
    });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    ).toBeTruthy();
    await page
        .getByRole("button", { name: "Открыть меню", exact: true })
        .click();
    await page.getByRole("link", { name: "Мои задачи", exact: true }).click();
    await expect(
        page.getByRole("heading", { name: "Мои задачи", exact: true }),
    ).toBeVisible();
    expect(errors).toEqual([]);
});
