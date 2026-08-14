document.addEventListener("DOMContentLoaded", () => {
    const verifyEmailLink = document.getElementById("verifyEmailLink");
    if (!verifyEmailLink) return;

    const closeActiveUniversalModal = async () => {
        const modalEl = document.getElementById("universalModal");
        if (!modalEl || !window.bootstrap?.Modal) return;
        const instance = bootstrap.Modal.getInstance(modalEl);
        if (!instance || !modalEl.classList.contains("show")) return;

        await new Promise((resolve) => {
            let fallbackTimer = 0;
            const finish = () => {
                modalEl.removeEventListener("shown.bs.modal", requestHide);
                modalEl.removeEventListener("hidden.bs.modal", finish);
                window.clearTimeout(fallbackTimer);
                resolve();
            };
            const requestHide = () => {
                if (!modalEl.classList.contains("show")) {
                    finish();
                    return;
                }
                instance.hide();
            };

            modalEl.addEventListener("shown.bs.modal", requestHide, { once: true });
            modalEl.addEventListener("hidden.bs.modal", finish, { once: true });
            requestHide();
            fallbackTimer = window.setTimeout(requestHide, 750);
        });
    };

    verifyEmailLink.addEventListener("click", async (e) => {
        e.preventDefault();

        const sendVerification = async () => {
            try {
                const controller = new AbortController();
                if (window.UniversalModal?.open) {
                    window.UniversalModal.open({
                        title: "Please Wait",
                        message: "Sending verification email...",
                        buttons: [
                            {
                                label: "Cancel",
                                class: "btn btn-outline-secondary",
                                onClick: () => controller.abort(),
                            },
                        ],
                    });
                }
                const res = await fetch("../PhpFiles/EmailHandlers/sendEmailVerify.php", {
                    method: "POST",
                    headers: { "Accept": "application/json" },
                    signal: controller.signal,
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    throw new Error(data.message || "Unable to send verification email.");
                }
                if (window.UniversalModal?.open) {
                    await closeActiveUniversalModal();
                    window.UniversalModal.open({
                        title: "Verification Email Sent",
                        messageHtml: "An email verification has been sent to your email. Click the verify button to proceed.<br><b>The verify link will expire in 15 minutes.</b>",
                        buttons: [{ label: "OK", class: "btn btn-primary" }],
                    });
                } else {
                    alert("Verification Email Sent\nCheck your inbox. The verify link will expire in 15 minutes.");
                }
            } catch (err) {
                if (err?.name === "AbortError" || err?.message === "Aborted" || err?.code === DOMException.ABORT_ERR) {
                    return;
                }
                if (window.UniversalModal?.open) {
                    await closeActiveUniversalModal();
                    window.UniversalModal.open({
                        title: "Error",
                        message: err?.message || "Unable to send verification email.",
                        buttons: [{ label: "OK", class: "btn btn-danger" }],
                    });
                } else {
                    alert(err?.message || "Unable to send verification email.");
                }
            }
        };

        const sendVerificationAfterPrompt = () => {
            const modalEl = document.getElementById("universalModal");
            if (modalEl?.classList.contains("show")) {
                modalEl.addEventListener("hidden.bs.modal", () => {
                    void sendVerification();
                }, { once: true });
                return;
            }
            void sendVerification();
        };

        if (window.UniversalModal?.open) {
            window.UniversalModal.open({
                title: "Verify Email",
                message: "Send a verification email to your registered address?",
                buttons: [
                    { label: "Cancel", class: "btn btn-outline-secondary" },
                    { label: "Send Email", class: "btn btn-primary", onClick: sendVerificationAfterPrompt },
                ],
            });
        } else {
            sendVerification();
        }
    });
});
