// Dashboard Workflow Management System
class DashboardWorkflow {
  constructor() {
    this.api = new StashSmartAPI();
    this.refreshInterval = null;
    this.init();
  }

  async init() {
    try {
      // Check authentication
      if (!this.api.isAuthenticated()) {
        this.redirectToLogin();
        return;
      }

      // Show loading state
      this.showLoadingState();

      // Load all dashboard components
      await Promise.all([
        this.loadUserProfile(),
        this.loadDashboardData(),
        this.checkBudgetAlerts(),
        this.loadInsights(),
      ]);

      // Setup event listeners
      this.setupEventListeners();

      // Setup auto-refresh
      this.setupAutoRefresh();

      // Hide loading state
      this.hideLoadingState();
    } catch (error) {
      console.error("Dashboard initialization error:", error);
      this.showError("Failed to load dashboard. Please refresh the page.");
    }
  }

  async loadUserProfile() {
    try {
      const response = await this.api.getProfile();
      if (response.success) {
        this.updateUserProfile(response.data);
      }
    } catch (error) {
      console.error("Failed to load user profile:", error);
    }
  }

  async loadDashboardData() {
    try {
      const response = await this.api.request("/workflows/dashboard");
      if (response.success) {
        this.updateAccountBalances(response.data.accounts);
        this.updateRecentTransactions(response.data.recent_transactions);
        this.updateMonthlySummary(response.data.monthly_summary);
        this.updateGoalProgress(response.data.goals);
      }
    } catch (error) {
      console.error("Failed to load dashboard data:", error);
    }
  }

  async checkBudgetAlerts() {
    try {
      const response = await this.api.request("/workflows/alerts");
      if (response.success && response.data.alerts.length > 0) {
        this.displayAlerts(response.data.alerts);
      }
    } catch (error) {
      console.error("Failed to check budget alerts:", error);
    }
  }

  async loadInsights() {
    try {
      const response = await this.api.request("/workflows/insights?period=30");
      if (response.success) {
        this.updateInsightCharts(response.data.insights);
      }
    } catch (error) {
      console.error("Failed to load insights:", error);
    }
  }

  updateUserProfile(user) {
    const elements = {
      userName: document.querySelector(".user-name"),
      userEmail: document.querySelector(".user-email"),
      userAvatar: document.querySelector(".user-avatar"),
    };

    if (elements.userName) elements.userName.textContent = user.full_name;
    if (elements.userEmail) elements.userEmail.textContent = user.email;
    if (elements.userAvatar && user.profile_picture) {
      elements.userAvatar.src = user.profile_picture;
    }
  }

  updateAccountBalances(accounts) {
    const container = document.querySelector(".accounts-container");
    if (!container) return;

    let totalBalance = 0;
    const accountsHTML = accounts
      .map((account) => {
        totalBalance += parseFloat(account.balance);
        return `
                <div class="account-card" data-account-id="${account.id}">
                    <div class="account-header">
                        <h3>${account.name}</h3>
                        <span class="account-type">${account.type}</span>
                    </div>
                    <div class="account-balance">
                        ${formatCurrency(account.balance)}
                    </div>
                </div>
            `;
      })
      .join("");

    container.innerHTML = accountsHTML;

    // Update total balance
    const totalBalanceElement = document.querySelector(".total-balance");
    if (totalBalanceElement) {
      totalBalanceElement.textContent = formatCurrency(totalBalance);
    }
  }

  updateRecentTransactions(transactions) {
    const container = document.querySelector(".recent-transactions");
    if (!container) return;

    if (transactions.length === 0) {
      container.innerHTML = '<p class="no-data">No recent transactions</p>';
      return;
    }

    const transactionsHTML = transactions
      .map(
        (transaction) => `
            <div class="transaction-item" data-transaction-id="${
              transaction.id
            }">
                <div class="transaction-icon" style="background-color: ${
                  transaction.category_color
                }">
                    <i class="${
                      transaction.category_icon || "fas fa-circle"
                    }"></i>
                </div>
                <div class="transaction-details">
                    <div class="transaction-description">${
                      transaction.description
                    }</div>
                    <div class="transaction-meta">
                        ${transaction.category_name} • ${
          transaction.account_name
        } • ${formatDate(transaction.transaction_date)}
                    </div>
                </div>
                <div class="transaction-amount ${transaction.type}">
                    ${
                      transaction.type === "income" ? "+" : "-"
                    }${formatCurrency(transaction.amount)}
                </div>
            </div>
        `
      )
      .join("");

    container.innerHTML = transactionsHTML;
  }

  updateMonthlySummary(summary) {
    const elements = {
      income: document.querySelector(".monthly-income"),
      expenses: document.querySelector(".monthly-expenses"),
      net: document.querySelector(".monthly-net"),
      incomeCount: document.querySelector(".income-count"),
      expenseCount: document.querySelector(".expense-count"),
    };

    if (elements.income)
      elements.income.textContent = formatCurrency(summary.income || 0);
    if (elements.expenses)
      elements.expenses.textContent = formatCurrency(summary.expense || 0);
    if (elements.net) {
      elements.net.textContent = formatCurrency(summary.net || 0);
      elements.net.className = `monthly-net ${
        summary.net >= 0 ? "positive" : "negative"
      }`;
    }
    if (elements.incomeCount)
      elements.incomeCount.textContent = summary.income_count || 0;
    if (elements.expenseCount)
      elements.expenseCount.textContent = summary.expense_count || 0;
  }

  updateGoalProgress(goals) {
    const container = document.querySelector(".goals-container");
    if (!container) return;

    if (goals.length === 0) {
      container.innerHTML = '<p class="no-data">No active goals</p>';
      return;
    }

    const goalsHTML = goals
      .map((goal) => {
        const progress =
          goal.target_amount > 0
            ? (goal.current_amount / goal.target_amount) * 100
            : 0;
        return `
                <div class="goal-card" data-goal-id="${goal.id}">
                    <div class="goal-header">
                        <h4>${goal.title}</h4>
                        <span class="goal-target">${formatCurrency(
                          goal.target_amount
                        )}</span>
                    </div>
                    <div class="goal-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${Math.min(
                              progress,
                              100
                            )}%"></div>
                        </div>
                        <div class="progress-text">
                            ${formatCurrency(
                              goal.current_amount
                            )} of ${formatCurrency(
          goal.target_amount
        )} (${progress.toFixed(1)}%)
                        </div>
                    </div>
                    ${
                      goal.target_date
                        ? `<div class="goal-date">Target: ${formatDate(
                            goal.target_date
                          )}</div>`
                        : ""
                    }
                </div>
            `;
      })
      .join("");

    container.innerHTML = goalsHTML;
  }

  displayAlerts(alerts) {
    const container = document.querySelector(".alerts-container");
    if (!container) {
      // Create alerts container if it doesn't exist
      const alertsContainer = document.createElement("div");
      alertsContainer.className = "alerts-container";
      document.querySelector(".dashboard-content").prepend(alertsContainer);
    }

    const alertsHTML = alerts
      .map(
        (alert) => `
            <div class="alert alert-${
              alert.type === "budget_exceeded" ? "danger" : "warning"
            }">
                <div class="alert-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="alert-content">
                    <div class="alert-title">${
                      alert.type === "budget_exceeded"
                        ? "Budget Exceeded"
                        : "Budget Alert"
                    }</div>
                    <div class="alert-message">${alert.message}</div>
                </div>
                <button class="alert-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `
      )
      .join("");

    document.querySelector(".alerts-container").innerHTML = alertsHTML;
  }

  updateInsightCharts(insights) {
    // Update category spending chart
    if (insights.category_spending && insights.category_spending.length > 0) {
      this.createCategoryChart(insights.category_spending);
    }

    // Update income vs expense chart
    if (insights.income_expense) {
      this.createIncomeExpenseChart(insights.income_expense);
    }

    // Update spending trends
    if (insights.trends) {
      this.createTrendsChart(insights.trends);
    }
  }

  createCategoryChart(data) {
    const canvas = document.querySelector("#categoryChart");
    if (!canvas) return;

    const ctx = canvas.getContext("2d");
    new Chart(ctx, {
      type: "doughnut",
      data: {
        labels: data.map((item) => item.name),
        datasets: [
          {
            data: data.map((item) => item.total),
            backgroundColor: data.map((item) => item.color),
            borderWidth: 2,
            borderColor: "#fff",
          },
        ],
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: "bottom",
          },
        },
      },
    });
  }

  createIncomeExpenseChart(data) {
    const canvas = document.querySelector("#incomeExpenseChart");
    if (!canvas) return;

    const income = data.find((item) => item.type === "income")?.total || 0;
    const expense = data.find((item) => item.type === "expense")?.total || 0;

    const ctx = canvas.getContext("2d");
    new Chart(ctx, {
      type: "bar",
      data: {
        labels: ["This Month"],
        datasets: [
          {
            label: "Income",
            data: [income],
            backgroundColor: "#28a745",
            borderColor: "#1e7e34",
            borderWidth: 1,
          },
          {
            label: "Expenses",
            data: [expense],
            backgroundColor: "#dc3545",
            borderColor: "#c82333",
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function (value) {
                return formatCurrency(value);
              },
            },
          },
        },
      },
    });
  }

  setupEventListeners() {
    // Add transaction button
    const addTransactionBtn = document.querySelector(".add-transaction-btn");
    if (addTransactionBtn) {
      addTransactionBtn.addEventListener("click", () =>
        this.showAddTransactionModal()
      );
    }

    // Refresh button
    const refreshBtn = document.querySelector(".refresh-btn");
    if (refreshBtn) {
      refreshBtn.addEventListener("click", () => this.refresh());
    }

    // Account cards click handlers
    document.addEventListener("click", (e) => {
      const accountCard = e.target.closest(".account-card");
      if (accountCard) {
        const accountId = accountCard.dataset.accountId;
        this.showAccountDetails(accountId);
      }

      const transactionItem = e.target.closest(".transaction-item");
      if (transactionItem) {
        const transactionId = transactionItem.dataset.transactionId;
        this.showTransactionDetails(transactionId);
      }
    });
  }

  setupAutoRefresh() {
    // Refresh data every 5 minutes
    this.refreshInterval = setInterval(() => {
      this.loadDashboardData();
      this.checkBudgetAlerts();
    }, 5 * 60 * 1000);
  }

  async showAddTransactionModal() {
    // This would open a modal or navigate to add transaction page
    // For now, we'll show a simple prompt
    const amount = prompt("Enter transaction amount:");
    const description = prompt("Enter description:");

    if (amount && description) {
      try {
        const response = await this.api.createTransaction({
          amount: parseFloat(amount),
          description: description,
          type: parseFloat(amount) > 0 ? "income" : "expense",
          transaction_date: new Date().toISOString().split("T")[0],
          account_id: 1, // Default account
          category_id: 1, // Default category
        });

        if (response.success) {
          showNotification("Transaction added successfully!", "success");
          this.refresh();
        }
      } catch (error) {
        showNotification("Failed to add transaction", "error");
      }
    }
  }

  async refresh() {
    await this.loadDashboardData();
    await this.checkBudgetAlerts();
    showNotification("Dashboard refreshed", "success");
  }

  showLoadingState() {
    const loadingElements = document.querySelectorAll(".loading-placeholder");
    loadingElements.forEach((el) => (el.style.display = "block"));
  }

  hideLoadingState() {
    const loadingElements = document.querySelectorAll(".loading-placeholder");
    loadingElements.forEach((el) => (el.style.display = "none"));
  }

  showError(message) {
    showNotification(message, "error");
  }

  redirectToLogin() {
    window.location.href = "/StashSmart/login/login.html";
  }

  destroy() {
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
    }
  }
}

// Transaction Workflow
class TransactionWorkflow {
  constructor() {
    this.api = new StashSmartAPI();
  }

  async createTransaction(data) {
    try {
      const response = await this.api.request("/workflows/transaction", {
        method: "POST",
        body: JSON.stringify(data),
      });

      if (response.success) {
        // Trigger dashboard refresh
        if (window.dashboardWorkflow) {
          window.dashboardWorkflow.refresh();
        }
        return response;
      } else {
        throw new Error(response.message);
      }
    } catch (error) {
      console.error("Transaction creation failed:", error);
      throw error;
    }
  }
}

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  // Check if we're on the dashboard page
  if (document.querySelector(".dashboard-content")) {
    window.dashboardWorkflow = new DashboardWorkflow();
  }

  // Initialize transaction workflow for transaction pages
  if (document.querySelector(".transaction-form")) {
    window.transactionWorkflow = new TransactionWorkflow();
  }
});

// Clean up on page unload
window.addEventListener("beforeunload", () => {
  if (window.dashboardWorkflow) {
    window.dashboardWorkflow.destroy();
  }
});
