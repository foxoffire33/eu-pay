import { createTheme, MantineColorsTuple } from '@mantine/core';

const eupay: MantineColorsTuple = [
  '#e8edff',
  '#c5d0ff',
  '#9eb0ff',
  '#7490ff',
  '#4a70ff',
  '#1a50ff',
  '#003399',
  '#002b80',
  '#002266',
  '#001a4d',
];

export const theme = createTheme({
  primaryColor: 'eupay',
  colors: { eupay },
  fontFamily:
    '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
  headings: {
    fontWeight: '700',
  },
  defaultRadius: 'md',
});
