import { test, expect } from "@playwright/test";

test("profile, themes, planning, pagination and team controls work on desktop and mobile", async ({
    page,
}) => {
    test.setTimeout(90000);
    const errors = [];
    page.on("pageerror", (e) => errors.push(e.message));
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto("http://127.0.0.1:8017/login");
    await page.getByLabel("Email", { exact: true }).fill("admin@mtis.test");
    await page.getByLabel("Пароль", { exact: true }).fill("Mtis-Demo-2026!");
    await page.getByRole("button", { name: "Войти", exact: true }).click();
    await expect(page.locator(".page-heading")).toBeVisible();
    await page
        .getByRole("button", { name: "Открыть профиль", exact: true })
        .click();
    const name = await page
        .getByRole("dialog")
        .getByLabel("ФИО", { exact: true })
        .inputValue();
    await page
        .getByRole("dialog")
        .getByLabel("ФИО", { exact: true })
        .fill(name + " QA");
    await page.getByLabel("Оформление", { exact: true }).selectOption("dark");
    await page
        .getByRole("button", { name: "Сохранить профиль", exact: true })
        .click();
    await expect(page.getByRole("dialog")).not.toBeVisible();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    await page.reload();
    await expect(page.locator(".page-heading")).toBeVisible();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    await page.screenshot({
        path: "storage/app/qa-dark.png",
        fullPage: true,
        animations: "disabled",
    });
    await page
        .getByRole("button", { name: "Открыть профиль", exact: true })
        .click();
    await expect(
        page.getByRole("dialog").getByLabel("ФИО", { exact: true }),
    ).toHaveValue(name + " QA");
    await page
        .getByRole("dialog")
        .getByLabel("ФИО", { exact: true })
        .fill(name);
    await page
        .getByRole("button", { name: "Сохранить профиль", exact: true })
        .click();
    await expect(page.getByRole("dialog")).not.toBeVisible();

    await page
        .locator(".sidebar")
        .getByRole("link", { name: "Все задачи", exact: true })
        .click();
    await page
        .getByLabel("Задач на странице", { exact: true })
        .selectOption("10");
    await expect(page.locator(".issue-table tbody tr")).toHaveCount(10);
    const firstKey = await page
        .locator(".issue-table tbody tr")
        .first()
        .locator(".issue-title-cell small")
        .textContent();
    await page
        .getByRole("button", { name: "Следующая страница", exact: true })
        .click();
    await expect(
        page
            .locator(".issue-table tbody tr")
            .first()
            .locator(".issue-title-cell small"),
    ).not.toHaveText(firstKey);
    await page
        .getByRole("button", { name: "Создать задачу", exact: true })
        .click();
    await expect(
        page.getByRole("dialog").getByLabel("Начало работ", { exact: true }),
    ).toHaveCount(0);
    await expect(
        page.getByRole("dialog").getByLabel("Компонент", { exact: true }),
    ).toHaveCount(0);
    await page
        .getByRole("button", { name: "Закрыть окно", exact: true })
        .click();

    await page
        .locator(".sidebar")
        .getByRole("link", { name: "Дорожная карта", exact: true })
        .click();
    await page
        .getByRole("button", { name: "Следующий год", exact: true })
        .click();
    await expect(page.locator(".roadmap-row").first()).toBeVisible();
    await page.screenshot({
        path: "storage/app/qa-roadmap-dark.png",
        fullPage: true,
        animations: "disabled",
    });
    await page
        .getByRole("button", { name: "Добавить работу", exact: true })
        .click();
    const title = "Проверка годового плана " + Date.now();
    await page
        .getByRole("dialog")
        .getByLabel("Название", { exact: true })
        .fill(title);
    await page
        .getByLabel("Длительность, рабочих дней", { exact: true })
        .fill("65");
    await page
        .getByRole("button", { name: "Сохранить план", exact: true })
        .click();
    await expect(page.getByRole("dialog")).not.toBeVisible();
    await page.getByRole("button", { name: title, exact: true }).click();
    await expect(
        page.getByLabel("Длительность, рабочих дней", { exact: true }),
    ).toHaveValue("65");
    await page
        .getByLabel("Состояние", { exact: true })
        .selectOption("cancelled");
    await page
        .getByRole("button", { name: "Сохранить план", exact: true })
        .click();
    await expect(page.getByRole("dialog")).not.toBeVisible();
    await page
        .getByRole("button", { name: "Светлая тема", exact: true })
        .click();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "light");
    await page.screenshot({
        path: "storage/app/qa-roadmap-light.png",
        fullPage: true,
        animations: "disabled",
    });

    await page
        .locator(".sidebar")
        .getByRole("link", { name: "Команда", exact: true })
        .click();
    await page
        .getByLabel("Команда сотрудников", { exact: true })
        .selectOption({ label: "Цифровые сервисы" });
    await page
        .getByRole("button", { name: "Изменить состав", exact: true })
        .click();
    await expect(
        page.getByRole("heading", { name: "Состав команды", exact: true }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Отмена", exact: true }).click();
    await page.getByRole("button").filter({ hasText: "Иван Иванов" }).click();
    await expect(
        page
            .getByRole("dialog")
            .getByRole("heading", { name: "Иван Иванов", exact: true }),
    ).toBeVisible();
    await page
        .getByRole("button", { name: "Закрыть окно", exact: true })
        .click();
    await page.setViewportSize({ width: 390, height: 844 });
    await page
        .getByRole("button", { name: "Темная тема", exact: true })
        .click();
    await page
        .getByRole("button", { name: "Открыть меню", exact: true })
        .click();
    await page
        .locator(".sidebar")
        .getByRole("link", { name: "Дорожная карта", exact: true })
        .click();
    await expect(page.locator(".mobile-shade")).not.toBeVisible();
    await expect(
        page.getByRole("heading", { name: "Дорожная карта", exact: true }),
    ).toBeVisible();
    await page.screenshot({
        path: "storage/app/qa-roadmap-mobile.png",
        fullPage: false,
        animations: "disabled",
    });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    ).toBeTruthy();
    await page
        .getByRole("button", { name: "Добавить работу", exact: true })
        .click();
    await page.screenshot({
        path: "storage/app/qa-plan-mobile.png",
        fullPage: false,
        animations: "disabled",
    });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    ).toBeTruthy();
    await page.getByRole("button", { name: "Отмена", exact: true }).click();
    await page
        .getByRole("button", { name: "Светлая тема", exact: true })
        .click();
    expect(errors).toEqual([]);
});
