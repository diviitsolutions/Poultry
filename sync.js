window.addEventListener("online", () => {
  syncExpenses();
  syncSales();
  syncEggProduction();
  syncLiabilities();
  syncUsers();
});

function syncExpenses() {
  let transaction = db.transaction(["expenses"], "readonly");
  let store = transaction.objectStore("expenses");
  let request = store.getAll();

  request.onsuccess = () => {
    let expenses = request.result;
    expenses.forEach(expense => {
      fetch("process_expenses.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(expense)
      })
      .then(response => response.json())
      .then(data => {
        if (data.status === "success") {
          let deleteTransaction = db.transaction(["expenses"], "readwrite");
          let deleteStore = deleteTransaction.objectStore("expenses");
          deleteStore.delete(expense.id);
          console.log("Expense synced and removed from IndexedDB");
        }
      })
      .catch(error => console.error("Sync Error:", error));
    });
  };
}

function syncSales() {
  // Similar function for sales
}

function syncEggProduction() {
  // Similar function for egg production
}

function syncLiabilities() {
  // Similar function for liabilities
}

function syncUsers() {
  // Similar function for users
}
