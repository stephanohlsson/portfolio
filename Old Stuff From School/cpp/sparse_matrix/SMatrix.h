/** Stephan Ohlsson
** z3389772 
** stephan.ohlsson@gmail.com
** Sparse Matrix implementation - efficiently perform operations on large, sparse, matrices
** Internal implementation concepts:
** vals_ is an array representing the non-zero matrix entries
** cidx_ is an array representing the matrix column indices, corresponding to vals_
** ridx_ is a map, which maps a row to a pair. 
** The first element of the pair contains the index of vals_/cidx_ corresponding to the first (non-zero) value in the row.
** The second element of the pair contains the number of (non-zero) entries in the row.
** In this way, only non-zero values are stored in memory. 
**/


#ifndef SMATRIX_H
#define SMATRIX_H

#include <exception>
#include <iostream>
#include <iomanip>
#include <fstream>
#include <map>
#include <string>
#include <utility>
#include <algorithm>
#include <cstddef>
#include <set>
#include <stdio.h>
#include <string.h>
// matrix error class
class MatrixError : public std::exception {
 public:
  MatrixError(const std::string& what_arg) : _what(what_arg) { }
  virtual const char* what() const throw() { return _what.c_str (); }
  virtual ~MatrixError() throw() { }
 private:
  std::string _what;
};


// sparse matrix class
class SMatrix {
 public:

  // types
  typedef size_t size_type;

  // friends
  friend bool operator==(const SMatrix&, const SMatrix&);
  friend bool operator!=(const SMatrix&, const SMatrix&);
  friend SMatrix operator+(const SMatrix&, const SMatrix&) throw(MatrixError); 
  friend SMatrix operator-(const SMatrix&, const SMatrix&) throw(MatrixError); 
  friend SMatrix operator*(const SMatrix&, const SMatrix&) throw(MatrixError); 
  friend SMatrix transpose(const SMatrix&);
  friend std::ostream& operator<<(std::ostream&, const SMatrix&);
  
  // constructors
  SMatrix(size_type, size_type);
  SMatrix(std::istream&);
  SMatrix(const SMatrix&);

  // operators  
  SMatrix& operator=(const SMatrix&); 
  SMatrix& operator+=(const SMatrix&) throw(MatrixError);
  SMatrix& operator-=(const SMatrix&) throw(MatrixError);
  SMatrix& operator*=(const SMatrix&) throw(MatrixError);
  int operator()(size_type, size_type) const throw(MatrixError);
  
  // operations
  inline size_type rows() const { return numRows; }
  inline size_type cols() const { return numCols; }
  bool setVal(size_type, size_type, int) throw(MatrixError);
  void addRows(size_type, size_type) throw(MatrixError);
  void addCols(size_type, size_type) throw(MatrixError);
  void subRows(size_type, size_type) throw(MatrixError);
  void subCols(size_type, size_type) throw(MatrixError);
  void swapRows(size_type, size_type) throw(MatrixError);
  void swapCols(size_type, size_type) throw(MatrixError);

  // `iterator' operations
  void begin() const;
  bool end() const;
  void next() const;
  int value() const;

  // destructor
  ~SMatrix();
  
  // static members  
  static SMatrix identity(size_type);

 private:
  // private data members
  int *vals_;
  size_type *cidx_;
  std::map< size_type, std::pair<size_t, unsigned int> > ridx_;

  // set to keep track of used columns
  std::set <size_t> col_;

  size_type numRows;
  size_type numCols;
  size_type numVals;
  size_type alloc_size;
  mutable size_type itrRow;
  mutable size_type itrCol;

  // Helper functions for setVal
  void insertNewRow(size_type, size_type, int);
  void insertExistingRow(size_type, size_type, int);
  void insertZero(size_type, size_type); 
  void alloc();
  void shift_left(size_type, unsigned int);
  void shift_right(size_type, unsigned int); 
};

#endif
