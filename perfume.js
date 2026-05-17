document.addEventListener("DOMContentLoaded", () => {

    const max = Number(window.maxPerfumes || 0);

    const cards = document.querySelectorAll(".perfCard");

    function getCheckedCount() {
        return document.querySelectorAll('input[name="perfumes[]"]:checked').length;
    }

    cards.forEach(card => {

        card.addEventListener("click", (e) => {

            const checkbox = card.querySelector('input[type="checkbox"]');

            const checkedCount = getCheckedCount();

            // If trying to CHECK a new one
            if (!checkbox.checked && checkedCount >= max) {
                e.preventDefault();
                e.stopImmediatePropagation();
                return false;
            }
        }, true);

    });

});

