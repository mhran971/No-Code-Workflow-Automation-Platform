import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Navigate, Route, Routes } from "react-router-dom";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { Toaster } from "@/components/ui/toaster";
import { TooltipProvider } from "@/components/ui/tooltip";
import { AuthProvider } from "@/contexts/AuthContext";
import { ProtectedRoute } from "@/components/ProtectedRoute";
import Login from "./pages/Login.tsx";
import Index from "./pages/Index.tsx";
import WorkflowList from "./pages/WorkflowList.tsx";
import WorkflowCreate from "./pages/WorkflowCreate.tsx";
import WorkflowTemplates from "./pages/WorkflowTemplates.tsx";
import PublicForm from "./pages/PublicForm.tsx";
import NotFound from "./pages/NotFound.tsx";

const queryClient = new QueryClient();

const App = () => (
  <QueryClientProvider client={queryClient}>
    <TooltipProvider>
      <Toaster />
      <Sonner />
      <BrowserRouter>
        <AuthProvider>
          <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/forms/:publicToken" element={<PublicForm />} />
            <Route
              path="/"
              element={
                <ProtectedRoute>
                  <Navigate to="/workflows" replace />
                </ProtectedRoute>
              }
            />
            <Route
              path="/workflows"
              element={
                <ProtectedRoute>
                  <WorkflowList />
                </ProtectedRoute>
              }
            />
            <Route
              path="/workflows/new"
              element={
                <ProtectedRoute>
                  <WorkflowCreate />
                </ProtectedRoute>
              }
            />
            <Route
              path="/workflows/templates"
              element={
                <ProtectedRoute>
                  <WorkflowTemplates />
                </ProtectedRoute>
              }
            />
            <Route
              path="/workflows/:id/canvas"
              element={
                <ProtectedRoute>
                  <Index />
                </ProtectedRoute>
              }
            />
            {/* ADD ALL CUSTOM ROUTES ABOVE THE CATCH-ALL "*" ROUTE */}
            <Route path="*" element={<NotFound />} />
          </Routes>
        </AuthProvider>
      </BrowserRouter>
    </TooltipProvider>
  </QueryClientProvider>
);

export default App;
