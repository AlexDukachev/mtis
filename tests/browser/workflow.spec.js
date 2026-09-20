import { test, expect } from "@playwright/test";

test("all roles complete a task through the interface", async ({ page }) => {
    test.setTimeout(90000);
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await page.setViewportSize({ width: 1366, height: 900 });
    const login = async (username) => {
        await page.goto("http://127.0.0.1:8017/login");
        if (!page.url().endsWith("/login")) {
            await page
                .getByRole("button", { name: "Выйти", exact: true })
                .click();
        }
        await page
            .getByLabel("Email", { exact: true })
            .fill(username + "@mtis.test");
        await page
            .getByLabel("Пароль", { exact: true })
            .fill("Mtis-Demo-2026!");
        await page.getByRole("button", { name: "Войти", exact: true }).click();
        await expect(page.locator(".loading-screen")).not.toBeVisible();
        await expect(page.locator(".page-heading")).toBeVisible();
    };
    const title = "Проверка цикла " + Date.now();
    const open = async () => {
        await page
            .locator(".sidebar")
            .getByRole("link", { name: "Все задачи", exact: true })
            .click();
        await page
            .getByRole("textbox", { name: "Поиск задач", exact: true })
            .fill(title);
        await expect(page.locator(".issue-table tbody tr")).toHaveCount(1);
        await page.locator(".issue-table tbody tr").click();
        await expect(page.getByRole("dialog")).toBeVisible();
    };
    const transition = async (status, reason) => {
        const actions = {
            "Готова к разработке": "В очередь разработки",
            "В разработке": "Начать работу",
            "Ожидает тестирования": "Передать на проверку",
            "На тестировании": "Взять на проверку",
            "Возвращена разработчику": "Вернуть на доработку",
            "Готова к приемке": "Проверка пройдена",
            Закрыта: "Принять и закрыть",
        };
        await page
            .locator(".issue-actions")
            .getByRole("button", { name: actions[status], exact: true })
            .click();
        if (reason || status === "Закрыта") {
            if (reason)
                await page.getByLabel("Причина (обязательно)").fill(reason);
            await page
                .getByRole("button", {
                    name: "Подтвердить переход",
                    exact: true,
                })
                .click();
        }
        await expect(page.locator(".issue-meta-line")).toContainText(status);
        await expect(page.locator(".modal .danger")).not.toBeVisible();
    };
    await login("popova");
    await page
        .getByRole("button", { name: "Создать задачу", exact: true })
        .click();
    await page
        .getByRole("dialog")
        .getByLabel("Название", { exact: true })
        .fill(title);
    await page
        .getByRole("dialog")
        .getByLabel("Описание", { exact: true })
        .fill("Сквозная проверка ролей, истории и вложений.");
    await page
        .getByRole("dialog")
        .getByRole("button", { name: "Создать задачу", exact: true })
        .click();
    await expect(
        page.getByRole("dialog").getByRole("heading", { name: title }),
    ).toBeVisible();
    await page
        .getByLabel("Комментарий", { exact: true })
        .fill('<script>alert("xss")</script> Текст комментария');
    await page.getByRole("button", { name: "Отправить", exact: true }).click();
    await expect(page.locator(".timeline")).toContainText(
        '<script>alert("xss")</script>',
    );
    await page.getByLabel("Прикрепить файл", { exact: true }).setInputFiles({
        name: "acceptance.txt",
        mimeType: "text/plain",
        buffer: Buffer.from("Acceptance test attachment"),
    });
    await page.getByRole("button", { name: "Файлы 1", exact: true }).click();
    await expect(
        page.getByRole("link", { name: "acceptance.txt", exact: false }),
    ).toBeVisible();
    await page
        .getByRole("button", { name: "Закрыть окно", exact: true })
        .click();

    await login("manager");
    await open();
    await page
        .locator(".issue-actions")
        .getByRole("button", { name: "Назначить", exact: true })
        .click();
    await page
        .getByRole("dialog")
        .getByLabel("Разработчик", { exact: true })
        .selectOption({ label: "Алексей Сидоров" });
    await page
        .getByRole("dialog")
        .getByLabel("Тестировщик", { exact: true })
        .selectOption({ label: "Дарья Козлова" });
    await page
        .getByRole("dialog")
        .getByRole("button", { name: "Сохранить назначение", exact: true })
        .click();
    await expect(page.locator(".issue-properties")).toContainText(
        "Алексей Сидоров",
    );
    await transition("Готова к разработке");
    await page
        .getByRole("button", { name: "Закрыть окно", exact: true })
        .click();

    await login("sidorov");
    await open();
    await page
        .locator(".issue-actions")
        .getByRole("button", { name: "Начать работу", exact: true })
        .click();
    await page
        .getByLabel("Сколько часов потребуется?", { exact: true })
        .fill("4");
    await page
        .getByRole("dialog")
        .getByRole("button", { name: "Начать работу", exact: true })
        .click();
    await expect(page.locator(".issue-meta-line")).toContainText(
        "В разработке",
    );
    await page
        .getByRole("button", { name: "Записать время", exact: true })
        .click();
    await page.getByLabel("Затрачено, ч", { exact: true }).fill("0.5");
    await page
        .getByLabel("Осталось после этой работы, ч", { exact: true })
        .fill("0");
    await page
        .getByRole("button", { name: "Сохранить время", exact: true })
        .click();
    await expect(page.locator(".time-summary")).toContainText("0,5 ч");
    await transition("Ожидает тестирования");
    await page
        .getByRole("button", { name: "Закрыть окно", exact: true })
        .click();

    await login("kozlova");
    await open();
    await transition("На тестировании");
    await transition(
        "Возвращена разработчику",
        "Не сохраняется ведущий ноль в БИН.",
    );
    await page
        .getByRole("button", { name: "Закрыть окно", exact: true })
        .click();
    await login("sidorov");
    await open();
    await transition("В разработке");
    await transition("Ожидает тестирования");
    await page
        .getByRole("button", { name: "Закрыть окно", exact: true })
        .click();
    await login("kozlova");
    await open();
    await transition("На тестировании");
    await transition("Готова к приемке");
    await page
        .getByRole("button", { name: "Закрыть окно", exact: true })
        .click();
    await login("popova");
    await open();
    await transition("Закрыта");
    await expect(page.locator(".issue-meta-line")).toContainText("Возвраты: 1");
    await expect(page.locator(".timeline")).toContainText(
        "Не сохраняется ведущий ноль",
    );
    expect(errors).toEqual([]);
});
